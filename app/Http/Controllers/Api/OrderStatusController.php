<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\OrderStatusesRepository;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Carbon;

class OrderStatusController extends Controller
{
    protected OrderStatusesRepository $orderStatusesRepository;

    public function __construct(OrderStatusesRepository $orderStatusesRepository)
    {
        $this->orderStatusesRepository = $orderStatusesRepository;
    }

    public function index()
    {
        return response()->json($this->orderStatusesRepository->getItems());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'color' => 'nullable|string|max:32'
        ]);

        $status = $this->orderStatusesRepository->createItem($data);
        return response()->json($status, 201);
    }

    public function show($id)
    {
        $status = $this->orderStatusesRepository->findItem($id);
        return response()->json($status);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'color' => 'nullable|string|max:32'
        ]);

        $status = $this->orderStatusesRepository->updateItem($id, $data);
        return response()->json($status);
    }

    public function destroy($id)
    {
        try {
            $this->orderStatusesRepository->deleteItem($id);
            return response()->json(null, 204);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    public function activityLog($id)
    {
        $user = request()->user();
        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Нет доступа к логам'], 403);
        }
        $logs = Activity::where('log_name', 'order_status')
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
                    'title' => 'Название',
                    'color' => 'Цвет',
                    default => $key,
                };
                $changes[$label] = ['old' => $old, 'new' => $new];
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
}
