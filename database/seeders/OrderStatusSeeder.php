<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('order_statuses')->insert([
            [
                'id'    => 1,
                'title' => 'Новые',
                'color' => '#56CCF2',
            ],
            [
                'id'    => 2,
                'title' => 'Принято в работу',
                'color' => '#2F80ED',
            ],
            [
                'id'    => 3,
                'title' => 'Перенос',
                'color' => '#F2C94C',
            ],
            [
                'id'    => 4,
                'title' => 'Завершено',
                'color' => '#27AE60',
            ],
            [
                'id'    => 5,
                'title' => 'Отменено',
                'color' => '#EB5757',
            ],
        ]);
    }
}
