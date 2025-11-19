<?php

namespace App\Policies;

use App\Models\AgentReport;
use App\Models\User;

class AgentReportPolicy
{
    /**
     * Определить, может ли пользователь просматривать любые акты-отчеты.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Определить, может ли пользователь просматривать акт-отчет.
     */
    public function view(User $user, AgentReport $agentReport): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Определить, может ли пользователь создавать акты-отчеты.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Определить, может ли пользователь обновлять акт-отчет.
     */
    public function update(User $user, AgentReport $agentReport): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Определить, может ли пользователь удалять акт-отчет.
     */
    public function delete(User $user, AgentReport $agentReport): bool
    {
        return $user->role === 'admin';
    }
}



