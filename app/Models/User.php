<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, LogsActivity, HasRoles;

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
                'note',
                'bank_access_key',
                'bank_key_expires_at'
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

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
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
        'bank_access_key',
        'bank_key_expires_at',
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

    public function orders()
    {
        return $this->hasMany(Order::class, 'courier_id');
    }

    /**
     * Generate a new bank access key with 1 month expiration
     */
    public function generateBankAccessKey()
    {
        $this->bank_access_key = strtoupper(substr(md5(uniqid(rand(), true)), 0, 12));
        $this->bank_key_expires_at = now()->addMonth();
        $this->save();
        return $this->bank_access_key;
    }

    /**
     * Check if bank access key is valid and not expired
     */
    public function isBankKeyValid($key)
    {
        if ($this->role !== 'bank') {
            return false;
        }

        if (!$this->bank_access_key || !$this->bank_key_expires_at) {
            return false;
        }

        if (now()->isAfter($this->bank_key_expires_at)) {
            return false;
        }

        return $this->bank_access_key === $key;
    }

    /**
     * Get days until bank key expires
     */
    public function getBankKeyDaysLeft()
    {
        if (!$this->bank_key_expires_at) {
            return 0;
        }

        return max(0, now()->diffInDays($this->bank_key_expires_at, false));
    }

    /**
     * Check if bank key is expired
     */
    public function isBankKeyExpired()
    {
        return $this->bank_key_expires_at && now()->isAfter($this->bank_key_expires_at);
    }
}
