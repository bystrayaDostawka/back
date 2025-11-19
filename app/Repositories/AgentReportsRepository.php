<?php

namespace App\Repositories;

use App\Models\AgentReport;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AgentReportsRepository
{
    public function getItems($user, $filters = [])
    {
        $query = AgentReport::with(['banks', 'creator']);

        // Фильтр по банку
        if (!empty($filters['bank_id'])) {
            $query->whereHas('banks', function ($q) use ($filters) {
                $q->where('bank_id', $filters['bank_id']);
            });
        }

        // Фильтр по статусу
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Фильтр по периоду (дата создания акта)
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Фильтр по периоду отчета
        if (!empty($filters['period_from'])) {
            $query->where('period_to', '>=', $filters['period_from']);
        }

        if (!empty($filters['period_to'])) {
            $query->where('period_from', '<=', $filters['period_to']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function createItem(array $data, $userId)
    {
        Log::info('AgentReportsRepository createItem', [
            'data' => $data,
            'user_id' => $userId,
        ]);

        return DB::transaction(function () use ($data, $userId) {
            $data['created_by'] = $userId;
            $bankIds = $data['bank_ids'] ?? [];
            unset($data['bank_ids']);

            $report = AgentReport::create($data);
            $report->banks()->sync($bankIds);

            // Создаем связи с заказами
            if (!empty($data['orders'])) {
                // Проверяем, что заказы принадлежат указанному банку
                $orderIds = array_column($data['orders'], 'order_id');
                $orders = Order::whereIn('id', $orderIds)
                    ->whereIn('bank_id', $bankIds)
                    ->get();
                
                if ($orders->count() !== count($orderIds)) {
                    throw new \Illuminate\Validation\ValidationException(
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

            // Пересчитываем общую стоимость
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

            // Обновляем основные поля
            $report->update(collect($data)->except(['bank_ids', 'orders'])->toArray());

            // Если обновляются заказы
            if (isset($data['orders'])) {
                // Проверяем, что заказы принадлежат банку акта
                $orderIds = array_column($data['orders'], 'order_id');
                $allowedBankIds = $bankIds ?? $report->banks()->pluck('banks.id')->toArray();
                $orders = Order::whereIn('id', $orderIds)
                    ->whereIn('bank_id', $allowedBankIds)
                    ->get();
                
                if ($orders->count() !== count($orderIds)) {
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        ['orders' => ['Некоторые заказы не принадлежат выбранным банкам']]
                    );
                }

                // Удаляем старые связи
                $report->reportOrders()->delete();

                // Создаем новые связи
                foreach ($data['orders'] as $orderData) {
                    $report->reportOrders()->create([
                        'order_id' => $orderData['order_id'],
                        'delivery_cost' => $orderData['delivery_cost'] ?? 0,
                    ]);
                }

                // Пересчитываем общую стоимость
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

    /**
     * Получить заказы за период для предзаполнения формы
     */
    public function getOrdersForPeriod(array $bankIds, $periodFrom, $periodTo)
    {
        $bankIds = array_values(array_filter(array_map('intval', $bankIds)));
        if (empty($bankIds)) {
            return collect();
        }

        $orders = Order::with(['bank', 'courier', 'status'])
            ->whereIn('bank_id', $bankIds)
            ->whereBetween('delivery_at', [$periodFrom . ' 00:00:00', $periodTo . ' 23:59:59'])
            ->where('order_status_id', 4) // Только завершенные заказы
            ->orderBy('delivery_at')
            ->get();

        // Формируем массив с нулевой стоимостью (будет заполняться вручную)
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
                'delivery_cost' => 0, // По умолчанию 0, можно будет изменить
            ];
        });
    }
}

