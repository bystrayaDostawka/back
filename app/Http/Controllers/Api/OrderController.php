<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Order::with(['bank', 'courier', 'status']);

        if ($user->role === 'admin' || $user->role === 'manager') {
        } elseif ($user->role === 'bank') {
            $query->where('bank_id', $user->bank_id);
        } elseif ($user->role === 'courier') {
            $query->where('courier_id', $user->id);
        } else {
            return response()->json([], 403);
        }

        return $query->orderByDesc('id')->get();
    }

    public function store(Request $request)
    {
        $this->authorize('create', Order::class);

        $data = $request->validate([
            'bank_id'         => 'required|exists:banks,id',
            'product'         => 'required|string|max:255',
            'client_name'     => 'required|string|max:255',
            'client_phone'    => 'required|string|max:255',
            'client_address'  => 'required|string|max:255',
            'delivery_at'     => 'required|date',
            'courier_id'      => 'nullable|exists:users,id',
            'note'            => 'nullable|string',
            'declined_reason' => 'nullable|string',
        ]);
        $data['order_status_id'] = 1;
        $order = Order::create($data);
        return response()->json($order->load(['bank', 'courier', 'status']), 201);
    }

    public function show($id)
    {
        $order = Order::with(['bank', 'courier', 'status'])->findOrFail($id);
        $this->authorize('view', $order);
        return response()->json($order);
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $this->authorize('update', $order);
        $data = $request->validate([
            'bank_id'         => 'required|exists:banks,id',
            'product'         => 'required|string|max:255',
            'client_name'     => 'required|string|max:255',
            'client_phone'    => 'required|string|max:255',
            'client_address'  => 'required|string|max:255',
            'delivery_at'     => 'required|date',
            'deliveried_at'   => 'nullable|date',
            'courier_id'      => 'nullable|exists:users,id',
            'order_status_id' => 'required|exists:order_statuses,id',
            'note'            => 'nullable|string',
            'declined_reason' => 'nullable|string',
        ]);

        $order->update($data);
        return response()->json($order->load(['bank', 'courier', 'status']));
    }

    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $this->authorize('delete', $order);
        $order->delete();
        return response()->json(null, 204);
    }

    public function changeStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $this->authorize('update', $order);

        $data = $request->validate([
            'order_status_id' => 'required|exists:order_statuses,id',
        ]);

        $order->order_status_id = $data['order_status_id'];
        $order->save();

        return response()->json($order->load(['status']));
    }
    public function bulkDestroy(Request $request)
    {
        $this->authorize('delete', Order::class);

        $ids = (array) $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['message' => 'Ничего не выбрано'], 422);
        }

        $orders = Order::whereIn('id', $ids)->get();
        foreach ($orders as $order) {
            $this->authorize('delete', $order);
            $order->delete();
        }

        return response()->json(['deleted' => $ids], 200);
    }

    public function bulkUpdate(Request $request)
    {
        $this->authorize('update', Order::class);

        $ids = (array) $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['message' => 'Ничего не выбрано'], 422);
        }

        $fields = $request->except('ids');
        if (empty($fields)) {
            return response()->json(['message' => 'Ничего не выбрано'], 422);
        }

        $orders = Order::whereIn('id', $ids)->get();

        foreach ($orders as $order) {
            $this->authorize('update', $order);
            $order->update($fields);
        }

        return response()->json(['updated' => $ids], 200);
    }
}
