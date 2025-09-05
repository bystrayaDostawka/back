<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\UsersRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;
use App\Models\Bank;
use Illuminate\Support\Carbon;

class UserController extends Controller
{
    protected UsersRepository $usersRepository;

    public function __construct(UsersRepository $usersRepository)
    {
        $this->usersRepository = $usersRepository;
    }

    public function index(Request $request)
    {
        $filters = [
            'role' => $request->input('role'),
            'is_active' => $request->input('is_active'),
        ];

        $items = $this->usersRepository->getItems($filters);
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->hasRole('manager') && $request->input('role') === 'admin') {
            return response()->json(['message' => 'Менеджер не может создавать админов'], 403);
        }
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users',
            'password'  => 'required|string|min:6',
            'phone'     => ['nullable', 'regex:/^\\+?[0-9]{10,15}$/'],
            'role'      => ['required', Rule::in(['admin', 'manager', 'courier', 'bank'])],
            'bank_id'   => 'nullable|exists:banks,id',
            'is_active' => 'boolean',
            'note'      => 'nullable|string',
        ]);

        $user = $this->usersRepository->createItem($data);
        return response()->json($user, 201);
    }

    public function show($id)
    {
        $user = $this->usersRepository->findItem($id);
        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email,' . $id,
            'phone'     => ['nullable', 'regex:/^\\+?[0-9]{10,15}$/'],
            'role'      => ['required', Rule::in(['admin', 'manager', 'courier', 'bank'])],
            'bank_id'   => 'nullable|exists:banks,id',
            'is_active' => 'boolean',
            'note'      => 'nullable|string',
            'password'  => 'nullable|string|min:6',
        ]);

        $user = $this->usersRepository->updateItem($id, $data);
        return response()->json($user);
    }

    public function destroy($id)
    {
        try {
            $this->usersRepository->deleteItem($id);
            return response()->json(null, 204);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function activityLog($id)
    {
        $user = request()->user();
        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Нет доступа к логам'], 403);
        }
        $logs = Activity::where('log_name', 'user')
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
                    'name' => 'Имя',
                    'email' => 'Email',
                    'phone' => 'Телефон',
                    'role' => 'Роль',
                    'bank_id' => 'Банк',
                    'is_active' => 'Активен',
                    'note' => 'Комментарий',
                    default => $key,
                };
                if ($key === 'role') {
                    $roles = ['admin' => 'Админ', 'manager' => 'Менеджер', 'courier' => 'Курьер', 'bank' => 'Банк'];
                    $oldVal = $old !== null ? ($roles[$old] ?? $old) : null;
                    $newVal = $new !== null ? ($roles[$new] ?? $new) : null;
                } elseif ($key === 'bank_id') {
                    $oldVal = $old ? Bank::find($old)?->name : null;
                    $newVal = $new ? Bank::find($new)?->name : null;
                } elseif ($key === 'is_active') {
                    $oldVal = $old !== null ? ($old ? 'Да' : 'Нет') : null;
                    $newVal = $new !== null ? ($new ? 'Да' : 'Нет') : null;
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

    // Методы для мобильного приложения курьеров
    public function courierProfile()
    {
        $user = request()->user();

        if (!$user->hasRole('courier')) {
            return response()->json(['message' => 'Доступ запрещён'], 403);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'note' => $user->note,
        ]);
    }

    public function courierUpdateProfile(Request $request)
    {
        $user = request()->user();

        if (!$user->hasRole('courier')) {
            return response()->json(['message' => 'Доступ запрещён'], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'note' => 'sometimes|nullable|string',
        ]);

        $data = $request->only(['name', 'phone', 'note']);
        $user->update($data);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'note' => $user->note,
        ]);
    }
}
