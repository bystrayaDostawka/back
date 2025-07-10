<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'color'
    ];

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($model) {

            if (in_array($model->id, [1, 2, 3, 4, 5])) {
                throw new \Exception('Этот статус удалять нельзя!');
            }
        });
    }
}