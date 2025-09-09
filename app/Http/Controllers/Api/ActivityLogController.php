<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Carbon;

class ActivityLogController extends Controller
{
    public function batch(Request $request)
    {
        $logName = $request->input('log_name');
        $ids = (array) $request->input('ids', []);
        if (!$logName || empty($ids)) {
            return response()->json([]);
        }

        $cacheKey = 'activity_logs_' . $logName . '_' . md5(json_encode($ids));
        $result = cache()->remember($cacheKey, 30, function () use ($logName, $ids) {
            $logs = Activity::where('log_name', $logName)
                ->whereIn('subject_id', $ids)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('subject_id');

            $formatLog = function ($log) use ($logName) {
                $changes = [];
                $fields = array_unique(array_merge(
                    array_keys($log->properties['attributes'] ?? []),
                    array_keys($log->properties['old'] ?? [])
                ));
                foreach ($fields as $key) {
                    $old = $log->properties['old'][$key] ?? null;
                    $new = $log->properties['attributes'][$key] ?? null;
                    $label = $this->getFieldLabel($logName, $key);
                    [$oldVal, $newVal] = $this->formatField($logName, $key, $old, $new);
                    $changes[$label] = ['old' => $oldVal, 'new' => $newVal];
                }
                return [
                    'description' => $log->description,
                    'changes'     => $changes,
                    'user'        => $log->causer ? $log->causer->name : null,
                    'date'        => Carbon::parse($log->created_at)->format('d.m.Y H:i'),
                ];
            };

            return $logs->map(function ($logs) use ($formatLog) {
                return $logs->map($formatLog)->values();
            });
        });

        return response()->json($result);
    }

    protected function getFieldLabel($logName, $key)
    {
        $labels = [
            'order' => [
                'note' => 'Комментарий',
                'order_status_id' => 'Статус',
                'courier_id' => 'Курьер',
                'bank_id' => 'Банк',
                'delivery_at' => 'Дата доставки',
                'delivered_at' => 'Дата доставки',
                'declined_reason' => 'Причина отмены',
                'product' => 'Продукт',
                'name' => 'Имя',
                'surname' => 'Фамилия',
                'patronymic' => 'Отчество',
                'phone' => 'Телефон',
                'address' => 'Адрес',
            ],
            'bank' => [
                'name' => 'Название',
                'phone' => 'Телефон',
                'email' => 'Email',
            ],
            'order_status' => [
                'title' => 'Название',
                'color' => 'Цвет',
            ],
            'user' => [
                'name' => 'Имя',
                'email' => 'Email',
                'phone' => 'Телефон',
                'role' => 'Роль',
                'bank_id' => 'Банк',
                'is_active' => 'Активен',
                'note' => 'Комментарий',
            ],
        ];
        return $labels[$logName][$key] ?? $key;
    }

    protected function formatField($logName, $key, $old, $new)
    {
        if ($key === 'order_status_id') {
            $oldVal = $old ? \App\Models\OrderStatus::find($old)?->title : null;
            $newVal = $new ? \App\Models\OrderStatus::find($new)?->title : null;
        } elseif ($key === 'courier_id') {
            $oldVal = $old ? \App\Models\User::find($old)?->name : null;
            $newVal = $new ? \App\Models\User::find($new)?->name : null;
        } elseif ($key === 'bank_id') {
            $oldVal = $old ? \App\Models\Bank::find($old)?->name : null;
            $newVal = $new ? \App\Models\Bank::find($new)?->name : null;
        } elseif (in_array($key, ['delivery_at', 'delivered_at'])) {
            $oldVal = $old ? Carbon::parse($old)->format('d.m.Y H:i') : null;
            $newVal = $new ? Carbon::parse($new)->format('d.m.Y H:i') : null;
        } elseif ($key === 'role') {
            $roles = ['admin' => 'Админ', 'manager' => 'Менеджер', 'courier' => 'Курьер', 'bank' => 'Банк'];
            $oldVal = $old !== null ? ($roles[$old] ?? $old) : null;
            $newVal = $new !== null ? ($roles[$new] ?? $new) : null;
        } elseif ($key === 'is_active') {
            $oldVal = $old !== null ? ($old ? 'Да' : 'Нет') : null;
            $newVal = $new !== null ? ($new ? 'Да' : 'Нет') : null;
        } else {
            $oldVal = $old;
            $newVal = $new;
        }
        return [$oldVal, $newVal];
    }
}
