<?php

namespace App\Repositories;

use App\Models\AgentReport;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgentReportsRepository
{
    public function getItems($user, $filters = [])
    {
        $query = AgentReport::with(['banks', 'creator']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function createItem(array $data, $userId)
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['created_by'] = $userId;
            $bankIds = $data['bank_ids'] ?? [];
            unset($data['bank_ids']);

            $report = AgentReport::create($data);
            $report->banks()->sync($bankIds);

            if (!empty($data['orders'])) {
                $orderIds = array_column($data['orders'], 'order_id');
                $orders = Order::whereIn('id', $orderIds)
                    ->whereIn('bank_id', $bankIds)
                    ->get();
                
                if ($orders->count() !== count($orderIds)) {
                    throw new ValidationException(
                        validator([], []),
                        ['orders' => ['Некоторые заказы не принадлежат выбранным банкам']]
                    );
                }

                foreach ($data['orders'] as $orderData) {
                    $report->reportOrders()->create([
                        'order_id' => $orderData['order_id'],
                        'delivery_cost' => $orderData['delivery_cost'] ?? 0,
                    ]);
                }
            }

            $report->recalculateTotalCost();

            return $report->load(['banks', 'creator', 'reportOrders.order']);
        });
    }

    public function findItem($id)
    {
        return AgentReport::with(['banks', 'creator', 'reportOrders.order.bank', 'reportOrders.order.courier', 'reportOrders.order.status'])
            ->findOrFail($id);
    }

    public function updateItem($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $report = $this->findItem($id);

            $bankIds = $data['bank_ids'] ?? null;
            if ($bankIds !== null) {
                $report->banks()->sync($bankIds);
            }

            $report->update(collect($data)->except(['bank_ids', 'orders'])->toArray());

            if (isset($data['orders'])) {
                $orderIds = array_column($data['orders'], 'order_id');
                $allowedBankIds = $bankIds ?? $report->banks()->pluck('banks.id')->toArray();
                $orders = Order::whereIn('id', $orderIds)
                    ->whereIn('bank_id', $allowedBankIds)
                    ->get();
                
                if ($orders->count() !== count($orderIds)) {
                    throw new ValidationException(
                        validator([], []),
                        ['orders' => ['Некоторые заказы не принадлежат выбранным банкам']]
                    );
                }

                $report->reportOrders()->delete();

                foreach ($data['orders'] as $orderData) {
                    $report->reportOrders()->create([
                        'order_id' => $orderData['order_id'],
                        'delivery_cost' => $orderData['delivery_cost'] ?? 0,
                    ]);
                }

                $report->recalculateTotalCost();
            }

            return $report->load(['banks', 'creator', 'reportOrders.order']);
        });
    }

    public function deleteItem($id)
    {
        $report = $this->findItem($id);
        $report->delete();
        return true;
    }

    public function getOrdersForPeriod(array $bankIds, $periodFrom, $periodTo)
    {
        $bankIds = array_values(array_filter(array_map('intval', $bankIds)));
        if (empty($bankIds)) {
            return collect();
        }

        $orders = Order::with(['bank', 'courier', 'status'])
            ->whereIn('bank_id', $bankIds)
            ->whereBetween('delivery_at', [$periodFrom . ' 00:00:00', $periodTo . ' 23:59:59'])
            ->where('order_status_id', 4)
            ->orderBy('delivery_at')
            ->get();

        return $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'bank_id' => $order->bank_id,
                'bank_name' => $order->bank?->name,
                'order_number' => $order->order_number,
                'product' => $order->product,
                'name' => $order->name,
                'surname' => $order->surname,
                'phone' => $order->phone,
                'address' => $order->address,
                'delivery_at' => $order->delivery_at,
                'delivered_at' => $order->delivered_at,
                'courier' => $order->courier ? $order->courier->name : null,
                'delivery_cost' => 0,
            ];
        });
    }
}

