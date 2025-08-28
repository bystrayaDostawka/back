<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\OrdersRepository;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use App\Models\OrderStatus;
use App\Models\User;
use App\Models\Bank;
use Illuminate\Support\Carbon;
use App\Imports\OrdersImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Order;


class OrderController extends Controller
{
    protected OrdersRepository $ordersRepository;

    public function __construct(OrdersRepository $ordersRepository)
    {
        $this->ordersRepository = $ordersRepository;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = [
            'search'          => $request->query('search'),
            'bank_id'         => $request->query('bank_id'),
            'order_status_id' => $request->query('order_status_id'),
            'courier_id'      => $request->has('courier_id') ? $request->query('courier_id') : null,
            'delivery_at'     => $request->query('delivery_at'),
            'date_from'       => $request->query('date_from'),
            'date_to'         => $request->query('date_to'),
        ];
        $orders = $this->ordersRepository->getItems($user, $filters);
        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $this->authorize('create', \App\Models\Order::class);

        $data = $request->validate([
            'bank_id'         => 'required|exists:banks,id',
            'product'         => 'required|string|max:255',
            'name'            => 'required|string|max:255',
            'surname'         => 'required|string|max:255',
            'patronymic'      => 'required|string|max:255',
            'phone'           => 'required|string|max:255',
            'address'         => 'required|string|max:255',
            'delivery_at'     => 'required|date',
            'courier_id'      => 'nullable|exists:users,id',
            'note'            => 'nullable|string',
            'declined_reason' => 'nullable|string',
        ]);

        $order = $this->ordersRepository->createItem($data);
        return response()->json($order, 201);
    }

    public function show($id)
    {
        $order = $this->ordersRepository->findItem($id);
        $this->authorize('view', $order);
        return response()->json($order);
    }

    public function update(Request $request, $id)
    {
        $order = $this->ordersRepository->findItem($id);
        $this->authorize('update', $order);

        $data = $request->validate([
            'bank_id'         => 'required|exists:banks,id',
            'product'         => 'required|string|max:255',
            'name'            => 'required|string|max:255',
            'surname'         => 'required|string|max:255',
            'patronymic'      => 'required|string|max:255',
            'phone'           => 'required|string|max:255',
            'address'         => 'required|string|max:255',
            'delivery_at'     => 'required|date',
            'deliveried_at'   => 'nullable|date',
            'courier_id'      => 'nullable|exists:users,id',
            'order_status_id' => 'required|exists:order_statuses,id',
            'note'            => 'nullable|string',
            'declined_reason' => 'nullable|string',
        ]);

        // Проверка на Перенос или Отменено
        if (in_array($data['order_status_id'], [5, 6])) {
            if (empty($data['declined_reason'])) {
                return response()->json(['message' => 'Причина отмены обязательна для выбранного статуса'], 422);
            }
            if ($data['order_status_id'] == 5 && empty($data['delivery_at'])) {
                return response()->json(['message' => 'Новая дата обязательна для переноса'], 422);
            }
        }

        $order = $this->ordersRepository->updateItem($id, $data);
        return response()->json($order);
    }

    public function destroy($id)
    {
        $order = $this->ordersRepository->findItem($id);
        $this->authorize('delete', $order);

        $this->ordersRepository->deleteItem($id);
        return response()->json(null, 204);
    }

    public function changeStatus(Request $request, $id)
    {
        $order = $this->ordersRepository->findItem($id);
        $this->authorize('update', $order);

        $data = $request->validate([
            'order_status_id' => 'required|exists:order_statuses,id',
            'declined_reason' => 'nullable|string',
            'delivery_at'     => 'nullable|date',
        ]);

        if (in_array($data['order_status_id'], [5, 6])) {
            if (empty($data['declined_reason'])) {
                return response()->json(['message' => 'Причина отмены обязательна для выбранного статуса'], 422);
            }
            if ($data['order_status_id'] == 5 && empty($data['delivery_at'])) {
                return response()->json(['message' => 'Новая дата обязательна для переноса'], 422);
            }
        }

        $order = $this->ordersRepository->changeStatus($id, $data['order_status_id'], $data);
        return response()->json($order);
    }

    public function bulkDestroy(Request $request)
    {
        $this->authorize('delete', \App\Models\Order::class);

        $ids = (array) $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['message' => 'Ничего не выбрано'], 422);
        }

        $this->ordersRepository->bulkDestroy($ids);
        return response()->json(['deleted' => $ids], 200);
    }

    public function bulkUpdate(Request $request)
    {
        $this->authorize('update', \App\Models\Order::class);

        $ids = (array) $request->input('ids', []);
        $fields = $request->except('ids');

        if (empty($ids) || empty($fields)) {
            return response()->json(['message' => 'Ничего не выбрано'], 422);
        }

        // Проверка на Перенос или Отменено
        if (isset($fields['order_status_id']) && in_array($fields['order_status_id'], [5, 6])) {
            if (empty($fields['declined_reason'])) {
                return response()->json(['message' => 'Причина отмены обязательна для выбранного статуса'], 422);
            }
            if ($fields['order_status_id'] == 5 && empty($fields['delivery_at'])) {
                return response()->json(['message' => 'Новая дата обязательна для переноса'], 422);
            }
        }

        $this->ordersRepository->bulkUpdate($ids, $fields);
        return response()->json(['updated' => $ids], 200);
    }

    public function activityLog($id)
    {
        $user = request()->user();
        if ($user->hasRole('bank') || $user->hasRole('manager') || $user->hasRole('courier')) {
            return response()->json(['message' => 'Нет доступа к логам'], 403);
        }
        $logs = Activity::where('log_name', 'order')
            ->where('subject_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $result = $logs->map(function ($log) {
            $changes = [];
            $fields = array_unique(array_merge(
                array_keys($log->properties['attributes'] ?? []),
                array_keys($log->properties['old'] ?? [])
            ));
            foreach ($fields as $key) {
                $old = $log->properties['old'][$key] ?? null;
                $new = $log->properties['attributes'][$key] ?? null;
                $label = match ($key) {
                    'note' => 'Комментарий',
                    'order_status_id' => 'Статус',
                    'courier_id' => 'Курьер',
                    'bank_id' => 'Банк',
                    'delivery_at', 'deliveried_at' => 'Дата доставки',
                    'declined_reason' => 'Причина отмены',
                    'product' => 'Продукт',
                    'name' => 'Имя',
                    'surname' => 'Фамилия',
                    'patronymic' => 'Отчество',
                    'phone' => 'Телефон',
                    'address' => 'Адрес',
                    'order_number' => 'Номер заказа',
                    default => $key,
                };
                // Связанные поля
                if ($key === 'order_status_id') {
                    $oldVal = $old ? OrderStatus::find($old)?->title : null;
                    $newVal = $new ? OrderStatus::find($new)?->title : null;
                } elseif ($key === 'courier_id') {
                    $oldVal = $old ? User::find($old)?->name : null;
                    $newVal = $new ? User::find($new)?->name : null;
                } elseif ($key === 'bank_id') {
                    $oldVal = $old ? Bank::find($old)?->name : null;
                    $newVal = $new ? Bank::find($new)?->name : null;
                } elseif (in_array($key, ['delivery_at', 'deliveried_at'])) {
                    $oldVal = $old ? Carbon::parse($old)->format('d.m.Y H:i') : null;
                    $newVal = $new ? Carbon::parse($new)->format('d.m.Y H:i') : null;
                } else {
                    $oldVal = $old;
                    $newVal = $new;
                }
                $changes[$label] = ['old' => $oldVal, 'new' => $newVal];
            }
            return [
                'description' => $log->description,
                'changes'     => $changes,
                'user'        => $log->causer ? $log->causer->name : null,
                'date'        => Carbon::parse($log->created_at)->format('d.m.Y H:i'),
            ];
        });
        return response()->json($result);
    }

    public function importFromExcel(Request $request)
    {
        $user = $request->user();
        if (!$user->hasRole('bank') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Доступ запрещён'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $bankId = $user->bank_id;
        if (!$bankId) {
            return response()->json(['message' => 'Нет ни одного банка в системе'], 422);
        }

        try {
            Excel::import(new OrdersImport($bankId), $request->file('file'));
            return response()->json(['message' => 'Заявки успешно загружены']);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'failures' => $e->failures()
            ], 422);
        }
    }
}
