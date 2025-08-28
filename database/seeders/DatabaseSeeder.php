<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use App\Models\Order;
use Spatie\Permission\Models\Role;
use App\Models\User;

class DatabaseSeeder extends Seeder
{

    public function run(): void
    {
        $this->call([
            UserDemoSeeder::class,
            OrderStatusSeeder::class,
        ]);

        Order::factory(50)->create();

        // Сидирование ролей
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'manager']);
        Role::firstOrCreate(['name' => 'courier']);
        Role::firstOrCreate(['name' => 'bank']);

        // Массовое назначение ролей пользователям на основе поля role
        foreach (User::all() as $user) {
            if ($user->role) {
                $user->syncRoles([$user->role]);
            }
        }
    }
}
