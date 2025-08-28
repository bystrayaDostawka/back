<?php

namespace App\Repositories;

use App\Models\Bank;

class BanksRepository
{
    public function getItems()
    {
        return Bank::query()->orderBy('id', 'asc')->get();
    }

    public function findItem($id)
    {
        return Bank::query()->findOrFail($id);
    }

    public function createItem(array $data)
    {
        return Bank::create($data);
    }

    public function updateItem(int $id, array $data)
    {
        $bank = Bank::findOrFail($id);
        $bank->update($data);
        return $bank;
    }

    public function deleteItem(int $id): bool
    {
        $bank = Bank::findOrFail($id);
        return (bool) $bank->delete();
    }
}
