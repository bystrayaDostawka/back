<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentReportOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_report_id',
        'order_id',
        'delivery_cost',
    ];

    protected $casts = [
        'delivery_cost' => 'decimal:2',
    ];

    public function agentReport()
    {
        return $this->belongsTo(AgentReport::class, 'agent_report_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}


