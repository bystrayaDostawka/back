<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($user->role === 'admin' || $user->role === 'manager') return true;
        if ($user->role === 'bank')    return $order->bank_id === $user->bank_id;
        if ($user->role === 'courier') return $order->courier_id === $user->id;
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        // Тот же принцип
        return $this->view($user, $order);
    }

    public function delete(User $user, Order $order): bool
    {
        // Только админ и менеджер
        return in_array($user->role, ['admin', 'manager']);
    }

    public function create(User $user): bool
    {
        // Только админ, менеджер, банк
        return in_array($user->role, ['admin', 'manager', 'bank']);
    }
}
