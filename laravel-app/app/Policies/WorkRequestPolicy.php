<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkRequest;

class WorkRequestPolicy
{
    public function review(User $user, WorkRequest $record): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        if (! $user->isManager()) {
            return false;
        }

        if ($user->managedTeams()->where('name', $record->user->team)->exists()) {
            return true;
        }

        return $user->receivedDelegations()
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->whereHas('manager.managedTeams', fn ($query) => $query->where('name', $record->user->team))
            ->exists();
    }
}
