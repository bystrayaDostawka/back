<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsersRepository
{
    public function getItems($filters = [])
    {
        $query = User::query()->with('bank');

        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool)$filters['is_active']);
        }

        return $query->orderByDesc('id')->get();
    }

    public function findItem($id)
    {
        return User::with('bank')->findOrFail($id);
    }

    public function createItem(array $data)
    {
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user = User::create($data);
        return $user->load('bank');
    }

    public function updateItem($id, array $data)
    {
        $user = User::findOrFail($id);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        return $user->load('bank');
    }

    public function deleteItem($id): bool
    {
        $user = User::findOrFail($id);

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            throw new \Exception('Нельзя удалить последнего администратора');
        }

        return (bool) $user->delete();
    }
}
