<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\Bank;

class GenerateOrderNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:generate-numbers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate order numbers for existing orders';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting order number generation...');

        // Получаем все заказы без номеров, сгруппированные по банку
        $ordersWithoutNumbers = Order::whereNull('order_number')
            ->with('bank')
            ->orderBy('bank_id')
            ->orderBy('id')
            ->get();

        if ($ordersWithoutNumbers->isEmpty()) {
            $this->info('No orders without numbers found.');
            return;
        }

        $this->info("Found {$ordersWithoutNumbers->count()} orders without numbers.");

        $bar = $this->output->createProgressBar($ordersWithoutNumbers->count());
        $bar->start();

        $currentBankId = null;
        $currentNumber = 1;

        foreach ($ordersWithoutNumbers as $order) {
            // Если сменился банк, сбрасываем счетчик
            if ($currentBankId !== $order->bank_id) {
                $currentBankId = $order->bank_id;
                $currentNumber = 1;
            }

            // Генерируем номер заказа
            $prefix = $order->bank->order_prefix ?: substr($order->bank->name, 0, 2);
            $prefix = strtoupper(preg_replace('/[^A-Za-zА-Яа-я]/', '', $prefix));

            $orderNumber = $prefix . str_pad($currentNumber, 5, '0', STR_PAD_LEFT);

            // Проверяем, что номер уникален
            while (Order::where('order_number', $orderNumber)->exists()) {
                $currentNumber++;
                $orderNumber = $prefix . str_pad($currentNumber, 5, '0', STR_PAD_LEFT);
            }

            // Обновляем заказ
            $order->update(['order_number' => $orderNumber]);

            $currentNumber++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Order number generation completed successfully!');
    }
}
