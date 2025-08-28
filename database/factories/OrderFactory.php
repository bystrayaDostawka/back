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

        // Создаем заказы с разными статусами для тестирования статистики
        $statusId = $this->faker->randomElement([1, 2, 3, 4, 5, 6]); // Все возможные статусы
        $status = OrderStatus::find($statusId) ?: OrderStatus::first();

        return [
            'bank_id'         => $bank ? $bank->id : null,
            'product'         => $this->faker->randomElement(['Карта', 'Вклад', 'Кредит', 'Страхование']),
            'name'            => $this->faker->firstName(),
            'surname'         => $this->faker->lastName(),
            'patronymic'      => $this->faker->lastName ?? '',
            'phone'           => $this->faker->phoneNumber(),
            'address'         => $this->faker->address(),
            'delivery_at'     => $this->faker->dateTimeBetween('now', '+30 days'),
            'deliveried_at'   => $statusId == 4 ? $this->faker->dateTimeBetween('-30 days', 'now') : null, // Заполняем только для завершенных
            'courier_id'      => $courier ? $courier->id : null,
            'order_status_id' => $status ? $status->id : 1,
            'note'            => $this->faker->optional()->sentence(),
            'declined_reason' => $statusId == 6 ? $this->faker->sentence() : null, // Причина отмены только для отмененных
        ];
    }
}
