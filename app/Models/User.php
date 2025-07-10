<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'email',
                'phone',
                'role',
                'bank_id',
                'is_active',
                'note'
            ])
            ->useLogName('user')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return "Пользователь был {$this->eventNameToRus($eventName)}";
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

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'bank_id',
        'is_active',
        'note',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }
}
