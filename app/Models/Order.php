<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Order extends Model
{
    use HasFactory, LogsActivity;


    protected $fillable = [
        'bank_id',
        'product',
        'name',
        'surname',
        'patronymic',
        'phone',
        'address',
        'delivery_at',
        'delivery_time_range',
        'delivered_at',
        'courier_id',
        'order_status_id',
        'note',
        'courier_note',
        'declined_reason',
        'order_number',
    ];

    protected $casts = [
        'name' => 'encrypted',
        'surname' => 'encrypted',
        'patronymic' => 'encrypted',
        'phone' => 'encrypted',
        'address' => 'encrypted',
        'product' => 'encrypted',
        'note' => 'encrypted',
        'courier_note' => 'encrypted',
        'declined_reason' => 'encrypted',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }
    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
    public function status()
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    public function photos()
    {
        return $this->hasMany(OrderPhoto::class);
    }

    public function comments()
    {
        return $this->hasMany(OrderComment::class)->orderBy('created_at', 'desc');
    }

    public function files()
    {
        return $this->hasMany(OrderFile::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'bank_id',
                'product',
                'name',
                'surname',
                'patronymic',
                'phone',
                'address',
                'delivery_at',
                'delivered_at',
                'courier_id',
                'order_status_id',
                'note',
                'courier_note',
                'declined_reason',
                'order_number',
            ])
            ->useLogName('order')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return "Заказ был {$this->eventNameToRus($eventName)}";
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

    /**
     * Переопределяем метод delete для очистки записей активности
     */
    public function delete()
    {
        // Удаляем все записи активности для этого заказа
        \Spatie\Activitylog\Models\Activity::where('log_name', 'order')
            ->where('subject_id', $this->id)
            ->delete();
            
        return parent::delete();
    }

    /**
     * Генерирует уникальный номер заказа для банка
     */
    public function generateOrderNumber()
    {
        if (!$this->bank_id) {
            return null;
        }

        // Загружаем банк если он не загружен
        $bank = $this->bank ?: Bank::find($this->bank_id);
        
        if (!$bank) {
            return null;
        }

        $prefix = $bank->order_prefix ?: substr($bank->name, 0, 2);
        $prefix = strtoupper(preg_replace('/[^A-Za-zА-Яа-я]/', '', $prefix));

        // Получаем последний номер заказа для этого банка
        $lastOrder = self::where('bank_id', $this->bank_id)
            ->whereNotNull('order_number')
            ->orderByRaw('CAST(SUBSTRING(order_number, ' . (strlen($prefix) + 1) . ') AS UNSIGNED) DESC')
            ->first();

        if ($lastOrder && $lastOrder->order_number) {
            // Извлекаем номер из последнего заказа
            $lastNumber = (int) preg_replace('/[^0-9]/', '', $lastOrder->order_number);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Boot метод для автоматической генерации номера при создании
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (!$order->order_number) {
                $order->order_number = $order->generateOrderNumber();
            }
        });
    }
}
