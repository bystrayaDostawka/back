<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

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

        // Обработка даты истечения ключа банка
        if (isset($data['bank_key_expires_at']) && !empty($data['bank_key_expires_at'])) {
            try {
                // Парсим дату в любом формате
                $date = Carbon::parse($data['bank_key_expires_at']);
                $data['bank_key_expires_at'] = $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // Логируем ошибку и удаляем невалидную дату
                \Log::warning('Invalid bank_key_expires_at format', [
                    'value' => $data['bank_key_expires_at'],
                    'error' => $e->getMessage()
                ]);
                unset($data['bank_key_expires_at']);
            }
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

        // Обработка даты истечения ключа банка
        if (isset($data['bank_key_expires_at']) && !empty($data['bank_key_expires_at'])) {
            try {
                // Парсим дату в любом формате
                $date = Carbon::parse($data['bank_key_expires_at']);
                $data['bank_key_expires_at'] = $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // Логируем ошибку и удаляем невалидную дату
                \Log::warning('Invalid bank_key_expires_at format', [
                    'value' => $data['bank_key_expires_at'],
                    'error' => $e->getMessage()
                ]);
                unset($data['bank_key_expires_at']);
            }
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
