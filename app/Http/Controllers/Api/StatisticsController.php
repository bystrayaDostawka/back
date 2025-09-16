<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StatisticsController extends Controller
{
    public function getOrderStatistics(Request $request)
    {
        $period = $request->get('period', 'all'); // all, day, week, month, year, custom
        $courierId = $request->get('courier_id');
        $bankId = $request->get('bank_id');
        $from = $request->get('from');
        $to = $request->get('to');

        $query = Order::query();

        // Фильтр по курьеру если указан
        if ($courierId) {
            $query->where('courier_id', $courierId);
        }

        // Фильтр по банку если указан
        if ($bankId) {
            $query->where('bank_id', $bankId);
        }

        // Фильтр только выполненных заказов
        $query->where('order_status_id', 4); // Завершено

        // Применяем фильтр по времени
        if ($period !== 'all') {
            if ($period === 'custom' && $from && $to) {
                $query->whereBetween('delivered_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
            } else {
                $this->applyDateFilter($query, $period);
            }
        }

        // Получаем статистику по месяцам
        $data = $this->getMonthlyStatistics($query);

        return response()->json($data);
    }

    private function applyDateFilter($query, $period)
    {
        switch ($period) {
            case 'day':
                $query->where('created_at', '>=', Carbon::now()->subDays(1));
                break;
            case 'week':
                $query->where('created_at', '>=', Carbon::now()->subWeeks(1));
                break;
            case 'month':
                $query->where('created_at', '>=', Carbon::now()->subMonths(1));
                break;
            case 'year':
                $query->where('created_at', '>=', Carbon::now()->subYears(1));
                break;
        }
    }

    private function applyDateFilterForCreated($query, $period)
    {
        switch ($period) {
            case 'today':
                $query->where('created_at', '>=', Carbon::today());
                break;
            case 'this_week':
                $query->where('created_at', '>=', Carbon::now()->startOfWeek());
                break;
            case 'this_month':
                $query->where('created_at', '>=', Carbon::now()->startOfMonth());
                break;
            case 'this_year':
                $query->where('created_at', '>=', Carbon::now()->startOfYear());
                break;
        }
    }

    private function getDailyStatistics($query)
    {
        $startDate = Carbon::now()->subDays(30);

        return $query->select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count
                ];
            });
    }

    private function getWeeklyStatistics($query)
    {
        $startDate = Carbon::now()->subWeeks(12);

        return $query->select(
            DB::raw('YEARWEEK(created_at, 1) as week'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('week')
            ->orderBy('week')
            ->get()
            ->map(function ($item) {
                return [
                    'week' => $item->week,
                    'count' => $item->count
                ];
            });
    }

    private function getMonthlyStatistics($query)
    {
        $startDate = Carbon::now()->subMonths(12);

        return $query->select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'count' => $item->count
                ];
            });
    }

    private function getYearlyStatistics($query)
    {
        $startDate = Carbon::now()->subYears(5);

        return $query->select(
            DB::raw('YEAR(created_at) as year'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('year')
            ->orderBy('year')
            ->get()
            ->map(function ($item) {
                return [
                    'year' => $item->year,
                    'count' => $item->count
                ];
            });
    }

    private function getCustomStatistics($query, $from, $to)
    {
        return $query->select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count
                ];
            });
    }

    public function getCourierStatistics(Request $request)
    {
        $period = $request->get('period', 'all');
        $courierId = $request->get('courier_id');
        $bankId = $request->get('bank_id');
        $from = $request->get('from');
        $to = $request->get('to');

        $couriers = User::where('role', 'courier')->get();
        $statistics = [];

        foreach ($couriers as $courier) {
            // Если выбран конкретный курьер, пропускаем остальных
            if ($courierId && $courier->id != $courierId) {
                continue;
            }

            $query = Order::where('courier_id', $courier->id)
                ->where('order_status_id', 4); // Завершено

            // Фильтр по банку если указан
            if ($bankId) {
                $query->where('bank_id', $bankId);
            }

            // Применяем фильтр по времени
            if ($period !== 'all') {
                if ($period === 'custom' && $from && $to) {
                    $query->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
                } else {
                    $this->applyDateFilter($query, $period);
                }
            }

            $data = $this->getMonthlyStatistics($query);

            $statistics[] = [
                'courier' => [
                    'id' => $courier->id,
                    'name' => $courier->name
                ],
                'data' => $data
            ];
        }

        return response()->json($statistics);
    }

    public function getDashboardStats(Request $request)
    {
        $period = $request->get('period', 'all');
        $courierId = $request->get('courier_id');
        $bankId = $request->get('bank_id');
        $from = $request->get('from');
        $to = $request->get('to');

        Log::info('Dashboard stats request', [
            'period' => $period,
            'courier_id' => $courierId,
            'bank_id' => $bankId,
            'from' => $from,
            'to' => $to
        ]);

        // Создаем базовый запрос
        $baseQuery = Order::query();
        if ($courierId) {
            $baseQuery->where('courier_id', $courierId);
        }
        if ($bankId) {
            $baseQuery->where('bank_id', $bankId);
        }

        // Общая статистика (все время)
        $totalOrders = (clone $baseQuery)->count();
        $completedOrders = (clone $baseQuery)->where('order_status_id', 4)->count(); // Завершено
        $pendingOrders = (clone $baseQuery)->whereIn('order_status_id', [1, 2, 3])->count(); // Новые, Принято в работу, ждёт проверку
        $cancelledOrders = (clone $baseQuery)->where('order_status_id', 6)->count(); // Отменено
        $totalCouriers = User::where('role', 'courier')->count();
        $totalBanks = DB::table('banks')->count();

        // Статистика за выбранный период
        $periodQuery = (clone $baseQuery);
        if ($period !== 'all') {
            if ($period === 'custom' && $from && $to) {
                $periodQuery->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
            } else {
                $this->applyDateFilterForCreated($periodQuery, $period);
            }
        } else {
            // Если "все время", показываем за последние 30 дней
            $periodQuery->where('created_at', '>=', Carbon::now()->subDays(30));
        }

        $periodOrders = $periodQuery->count();
        $periodCompleted = (clone $periodQuery)->where('order_status_id', 4)->count(); // Завершено
        $periodCancelled = (clone $periodQuery)->where('order_status_id', 6)->count(); // Отменено

        // Дополнительная статистика по статусам за выбранный период
        $postponedOrders = (clone $periodQuery)->where('order_status_id', 5)->count(); // Перенос
        $pendingVerification = (clone $periodQuery)->where('order_status_id', 3)->count(); // Ждёт проверку
        $inWorkOrders = (clone $periodQuery)->where('order_status_id', 2)->count(); // В работе (Принято в работу)

        $result = [
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'pending_orders' => $pendingOrders,
            'cancelled_orders' => $cancelledOrders,
            'total_couriers' => $totalCouriers,
            'total_banks' => $totalBanks,
            'period_orders' => $periodOrders,
            'period_completed' => $periodCompleted,
            'period_cancelled' => $periodCancelled,
            'postponed_orders' => $postponedOrders,
            'pending_verification' => $pendingVerification,
            'in_work_orders' => $inWorkOrders
        ];

        Log::info('Dashboard stats result', $result);

        return response()->json($result);
    }

    /**
     * Получить статистику дашборда для курьера (мобильное приложение)
     */
    public function getCourierDashboardStats(Request $request)
    {
        $user = $request->user();

        // Проверяем, что пользователь - курьер
        if ($user->role !== 'courier') {
            return response()->json(['message' => 'Доступ запрещён'], 403);
        }

        $period = $request->get('period', 'today'); // today, this_week, this_month, this_year, all
        $from = $request->get('from');
        $to = $request->get('to');

        Log::info('Courier dashboard stats request', [
            'courier_id' => $user->id,
            'period' => $period,
            'from' => $from,
            'to' => $to
        ]);

        // Создаем базовый запрос для заказов курьера
        $baseQuery = Order::where('courier_id', $user->id);

        // Общая статистика (все время)
        $totalOrders = (clone $baseQuery)->count();
        $completedOrders = (clone $baseQuery)->where('order_status_id', 4)->count(); // Завершено
        $pendingOrders = (clone $baseQuery)->whereIn('order_status_id', [1, 2, 3])->count(); // Новые, Принято в работу, ждёт проверку
        $cancelledOrders = (clone $baseQuery)->where('order_status_id', 6)->count(); // Отменено
        $postponedOrders = (clone $baseQuery)->where('order_status_id', 5)->count(); // Перенос

        // Статистика за выбранный период
        $periodQuery = (clone $baseQuery);
        if ($period !== 'all') {
            if ($period === 'custom' && $from && $to) {
                $periodQuery->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
            } else {
                $this->applyDateFilterForCreated($periodQuery, $period);
            }
        }

        $periodOrders = $periodQuery->count();
        $periodCompleted = (clone $periodQuery)->where('order_status_id', 4)->count(); // Завершено
        $periodCancelled = (clone $periodQuery)->where('order_status_id', 6)->count(); // Отменено
        $periodPostponed = (clone $periodQuery)->where('order_status_id', 5)->count(); // Перенос
        $periodPendingVerification = (clone $periodQuery)->where('order_status_id', 3)->count(); // Ждёт проверку
        $periodInWork = (clone $periodQuery)->where('order_status_id', 2)->count(); // В работе

        // Вычисляем процент выполнения за период
        $activeOrders = $periodOrders - $periodCancelled;
        $completionRate = $activeOrders > 0 ? round(($periodCompleted / $activeOrders) * 100) : 0;

        // Статистика по статусам за период
        $statusStats = [
            'new' => (clone $periodQuery)->where('order_status_id', 1)->count(), // Новые
            'in_work' => $periodInWork, // В работе
            'pending_verification' => $periodPendingVerification, // Ждёт проверку
            'completed' => $periodCompleted, // Выполнено
            'postponed' => $periodPostponed, // Перенос
            'cancelled' => $periodCancelled // Отменено
        ];

        $result = [
            // Общая статистика
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'pending_orders' => $pendingOrders,
            'cancelled_orders' => $cancelledOrders,
            'postponed_orders' => $postponedOrders,

            // Статистика за период
            'period_orders' => $periodOrders,
            'period_completed' => $periodCompleted,
            'period_cancelled' => $periodCancelled,
            'period_postponed' => $periodPostponed,
            'period_pending_verification' => $periodPendingVerification,
            'period_in_work' => $periodInWork,

            // Процент выполнения
            'completion_rate' => $completionRate,

            // Детальная статистика по статусам
            'status_stats' => $statusStats,

            // Информация о курьере
            'courier' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ],

            // Период
            'period' => $period,
            'period_from' => $from,
            'period_to' => $to
        ];

        Log::info('Courier dashboard stats result', $result);

        return response()->json($result);
    }
}
