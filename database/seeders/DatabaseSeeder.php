<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use App\Models\Order;

class DatabaseSeeder extends Seeder
{

    public function run(): void
    {
        $this->call([
            UserDemoSeeder::class,
            OrderStatusSeeder::class,
        ]);

        Order::factory(50)->create();
    }
}
