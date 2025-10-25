<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;
use App\Models\Order;

class CleanupOrphanedActivityLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activity-log:cleanup-orphaned {--force : Удалить без подтверждения}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove activity log entries for non-existent orders';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Поиск записей активности для несуществующих заказов...');
        
        // Получаем все существующие ID заказов
        $existingOrderIds = Order::pluck('id')->toArray();
        
        // Находим записи активности для несуществующих заказов
        $orphanedLogs = Activity::where('log_name', 'order')
            ->whereNotIn('subject_id', $existingOrderIds)
            ->get();
        
        if ($orphanedLogs->isEmpty()) {
            $this->info('Осиротевших записей активности не найдено.');
            return;
        }
        
        $this->warn("Найдено {$orphanedLogs->count()} записей активности для несуществующих заказов:");
        
        foreach ($orphanedLogs as $log) {
            $orderNumber = $log->properties['attributes']['order_number'] ?? 'неизвестно';
            $this->line("- ID: {$log->id}, Subject ID: {$log->subject_id}, Order Number: {$orderNumber}, Created: {$log->created_at}");
        }
        
        $shouldDelete = $this->option('force') || $this->confirm('Удалить эти записи?');
        
        if ($shouldDelete) {
            $deletedCount = 0;
            foreach ($orphanedLogs as $log) {
                $log->delete();
                $deletedCount++;
            }
            
            $this->info("Удалено {$deletedCount} записей активности.");
        } else {
            $this->info('Операция отменена.');
        }
    }
}
