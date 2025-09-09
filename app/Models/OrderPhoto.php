<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class OrderPhoto extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'order_id',
        'file_path',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'order_id',
                'file_path'
            ])
            ->useLogName('order_photo')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return "Фотография заказа была {$this->eventNameToRus($eventName)}";
    }

    protected function eventNameToRus($event)
    {
        return match ($event) {
            'created' => 'добавлена',
            'updated' => 'обновлена',
            'deleted' => 'удалена',
            default   => $event,
        };
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Получить полный URL к файлу
    public function getUrlAttribute()
    {
        return config('app.url') . '/storage/' . $this->file_path;
    }
}
