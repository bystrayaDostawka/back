<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class OrderStatus extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'title',
        'color'
    ];

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($model) {

            if (in_array($model->id, [1, 2, 3, 4, 5, 6])) {
                throw new \Exception('Этот статус удалять нельзя!');
            }
        });
    }

    public function isTransfer(): bool
    {
        return $this->id === 3;
    }

    public function isCancelled(): bool
    {
        return $this->id === 5;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'color'])
            ->useLogName('order_status')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return "Статус заказа был {$this->eventNameToRus($eventName)}";
    }

    protected function eventNameToRus($event)
    {
        return match ($event) {
            'created' => 'создан',
            'updated' => 'обновлён',
            'deleted' => 'удалён',
            default   => $event,
        };
    }
}
