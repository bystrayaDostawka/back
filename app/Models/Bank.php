<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Bank extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'order_prefix'
    ];

    protected $casts = [
        'name' => 'encrypted',
        'phone' => 'encrypted',
        'email' => 'encrypted',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'phone', 'email', 'order_prefix'])
            ->useLogName('bank')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return "Банк был {$this->eventNameToRus($eventName)}";
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
