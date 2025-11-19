<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class AgentReport extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'period_from',
        'period_to',
        'delivery_cost',
        'status',
        'excel_file_path',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'delivery_cost' => 'decimal:2',
    ];

    public function banks()
    {
        return $this->belongsToMany(Bank::class, 'agent_report_bank');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'agent_report_orders')
            ->withPivot('delivery_cost')
            ->withTimestamps();
    }

    public function reportOrders()
    {
        return $this->hasMany(AgentReportOrder::class);
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'formed' => 'Сформирован',
            'under_review' => 'На рассмотрении',
            'approved' => 'Одобрен',
            'rejected' => 'Отклонён',
            default => $this->status,
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'period_from',
                'period_to',
                'delivery_cost',
                'status',
                'notes',
            ])
            ->useLogName('agent_report')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return "Акт-отчет агента был {$this->eventNameToRus($eventName)}";
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
     * Пересчитать общую стоимость доставки на основе связанных заказов
     */
    public function recalculateTotalCost()
    {
        $total = $this->reportOrders()->sum('delivery_cost');
        $this->delivery_cost = $total;
        $this->save();
        return $total;
    }
}



