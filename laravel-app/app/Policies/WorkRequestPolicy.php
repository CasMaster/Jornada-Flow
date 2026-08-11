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

        return $user->isManager() && $user->managedTeams()->where('name', $record->user->team)->exists();
    }
}
