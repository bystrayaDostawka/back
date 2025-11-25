<?php

namespace App\Policies;

use App\Models\AgentReport;
use App\Models\User;

class AgentReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, AgentReport $agentReport): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, AgentReport $agentReport): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, AgentReport $agentReport): bool
    {
        return $user->role === 'admin';
    }
}
