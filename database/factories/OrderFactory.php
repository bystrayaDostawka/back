<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Bank;
use App\Models\User;
use App\Models\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        // Получим существующий банк, статус и курьера
        $bank = Bank::inRandomOrder()->first();
        $courier = User::where('role', 'courier')->inRandomOrder()->first();
        $status = OrderStatus::inRandomOrder()->first();

        return [
            'bank_id'         => $bank ? $bank->id : null,
            'product'         => $this->faker->randomElement(['Карта', 'Вклад', 'Кредит', 'Страхование']),
            'client_name'     => $this->faker->name(),
            'client_phone'    => $this->faker->phoneNumber(),
            'client_address'  => $this->faker->address(),
            'delivery_at'     => $this->faker->dateTimeBetween('now', '+30 days'),
            'deliveried_at'   => null,
            'courier_id'      => $courier ? $courier->id : null,
            'order_status_id' => $status ? $status->id : 1,
            'note'            => $this->faker->optional()->sentence(),
            'declined_reason' => null,
        ];
    }
}
