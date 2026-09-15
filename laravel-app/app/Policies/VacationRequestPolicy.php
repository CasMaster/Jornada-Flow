<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VacationRequest;

class VacationRequestPolicy
{
    public function review(User $user, VacationRequest $vacation): bool
    {
        if ($user->role === 'super_admin') return true;
        if (! $user->isManager()) return false;

        if ($user->managedTeams()->where('name', $vacation->user->team)->exists()) return true;

        return $user->receivedDelegations()->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())
            ->whereHas('manager.managedTeams', fn ($query) => $query->where('name', $vacation->user->team))->exists();
    }
}
