<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Bank;
use Illuminate\Support\Facades\Hash;

class UserDemoSeeder extends Seeder
{
    public function run(): void
    {

        $bank = Bank::firstOrCreate([
            'name' => 'Тестовый банк',
            'phone' => '88005553535',
            'email' => 'bank@test.ru',
        ]);

        User::firstOrCreate(
            ['email' => 'admin@test.ru'],
            [
                'name'      => 'Администратор',
                'password'  => Hash::make('password'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'manager@test.ru'],
            [
                'name'      => 'Менеджер',
                'password'  => Hash::make('password'),
                'role'      => 'manager',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'courier@test.ru'],
            [
                'name'      => 'Курьер',
                'password'  => Hash::make('password'),
                'role'      => 'courier',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'bankuser@test.ru'],
            [
                'name'      => 'Пользователь банка',
                'password'  => Hash::make('password'),
                'role'      => 'bank',
                'is_active' => true,
                'bank_id'   => $bank->id,
            ]
        );
    }
}
