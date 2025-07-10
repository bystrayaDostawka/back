<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;


    protected $fillable = [
        'bank_id',
        'product',
        'client_name',
        'client_phone',
        'client_address',
        'delivery_at',
        'deliveried_at',
        'courier_id',
        'order_status_id',
        'note',
        'declined_reason',
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
}
