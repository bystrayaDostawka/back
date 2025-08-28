<?php

namespace App\Repositories;

use App\Models\Order;

class OrdersRepository
{
    public function getItems($user, $filters = [])
    {
        $query = Order::with(['bank', 'courier', 'status']);

        if ($user->role === 'bank') {
            $query->where('bank_id', $user->bank_id);
        } elseif ($user->role === 'courier') {
            $query->where('courier_id', $user->id);
        } elseif (!in_array($user->role, ['admin', 'manager'])) {
            abort(403);
        }

        // Поиск (id, номер заказа, имя, фамилия, телефон) от 3 символов
        if (!empty($filters['search']) && mb_strlen($filters['search']) >= 3) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->orWhere('id', $search)
                  ->orWhere('order_number', 'like', "%$search%")
                  ->orWhere('name', 'like', "%$search%")
                  ->orWhere('surname', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%")
                ;
            });
        }

        // Фильтр по банку
        if (!empty($filters['bank_id'])) {
            $query->where('bank_id', $filters['bank_id']);
        }
        // Фильтр по статусу
        if (!empty($filters['order_status_id'])) {
            $query->where('order_status_id', $filters['order_status_id']);
        }
        // Фильтр по курьеру
        if (array_key_exists('courier_id', $filters)) {
            if ($filters['courier_id'] === 'none') {
                $query->whereNull('courier_id');
            } elseif (!empty($filters['courier_id'])) {
                $query->where('courier_id', $filters['courier_id']);
            }
        }
        // Фильтр по дате (delivery_at)
        if (!empty($filters['delivery_at'])) {
            switch ($filters['delivery_at']) {
                case 'today':
                    $query->whereDate('delivery_at', now()->toDateString());
                    break;
                case 'yesterday':
                    $query->whereDate('delivery_at', now()->subDay()->toDateString());
                    break;
                case 'this_week':
                    $query->whereBetween('delivery_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereBetween('delivery_at', [now()->startOfMonth(), now()->endOfMonth()]);
                    break;
                default:
                    // Если передана конкретная дата
                    $query->whereDate('delivery_at', $filters['delivery_at']);
            }
        }
        // Кастомный диапазон дат
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereBetween('delivery_at', [$filters['date_from'], $filters['date_to']]);
        }

        return $query->orderByDesc('id')->get();
    }

    public function createItem(array $data)
    {
        $data['order_status_id'] = 1;
        $order = Order::create($data);
        return $order->load(['bank', 'courier', 'status']);
    }

    public function findItem($id)
    {
        return Order::with(['bank', 'courier', 'status'])->findOrFail($id);
    }

    public function updateItem($id, array $data)
    {
        $order = Order::findOrFail($id);
        $order->update($data);
        return $order->load(['bank', 'courier', 'status']);
    }

    public function deleteItem($id)
    {
        $order = Order::findOrFail($id);
        return $order->delete();
    }

    public function changeStatus($id, int $statusId, array $extra = [])
    {
        $order = Order::findOrFail($id);
        $order->order_status_id = $statusId;
        if (in_array($statusId, [5, 6])) {
            if (isset($extra['declined_reason'])) {
                $order->declined_reason = $extra['declined_reason'];
            }
            if ($statusId == 5 && isset($extra['delivery_at'])) {
                $order->delivery_at = $extra['delivery_at'];
            }
        }
        $order->save();
        return $order->load(['status']);
    }

    public function bulkDestroy(array $ids): array
    {
        $orders = Order::with(['status', 'bank', 'courier'])->whereIn('id', $ids)->get();
        foreach ($orders as $order) {
            $order->delete();
        }
        return $ids;
    }

    public function bulkUpdate(array $ids, array $fields): array
    {
        $orders = Order::with(['status', 'bank', 'courier'])->whereIn('id', $ids)->get();
        foreach ($orders as $order) {
            if (isset($fields['order_status_id']) && in_array($fields['order_status_id'], [5, 6])) {
                if (isset($fields['declined_reason'])) {
                    $order->declined_reason = $fields['declined_reason'];
                }
                if ($fields['order_status_id'] == 5 && isset($fields['delivery_at'])) {
                    $order->delivery_at = $fields['delivery_at'];
                }
                $order->order_status_id = $fields['order_status_id'];
                $order->save();
            } else {
                $order->update($fields);
            }
        }
        return $ids;
    }
}
