<?php

namespace App\Repositories;

use App\Models\OrderStatus;

class OrderStatusesRepository
{
    protected array $protectedIds = [1, 2, 3, 4, 5, 6];

    public function getItems()
    {
        return OrderStatus::query()->orderBy('id', 'asc')->get();
    }

    public function findItem(int $id)
    {
        return OrderStatus::query()->findOrFail($id);
    }

    public function createItem(array $data)
    {
        return OrderStatus::create($data);
    }

    public function updateItem(int $id, array $data)
    {
        $status = OrderStatus::findOrFail($id);
        $status->update($data);
        return $status;
    }

    public function deleteItem(int $id): bool
    {
        if (in_array($id, $this->protectedIds)) {
            throw new \Exception('Этот статус удалять нельзя');
        }

        $status = OrderStatus::findOrFail($id);
        return (bool) $status->delete();
    }
}
