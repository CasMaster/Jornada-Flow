<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VacationEntitlement extends Model
{
    protected $fillable = [
        'user_id', 'acquisition_starts_on', 'acquisition_ends_on', 'expires_on',
        'granted_days', 'adjustment_days', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'acquisition_starts_on' => 'date', 'acquisition_ends_on' => 'date', 'expires_on' => 'date',
        'granted_days' => 'integer', 'adjustment_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(VacationRequest::class);
    }

    public function totalDays(): int
    {
        return $this->granted_days + $this->adjustment_days;
    }

    public function approvedDays(?int $ignoreRequestId = null): int
    {
        return $this->requestDays(['approved'], $ignoreRequestId);
    }

    public function reservedDays(?int $ignoreRequestId = null): int
    {
        return $this->requestDays(['pending'], $ignoreRequestId);
    }

    public function availableDays(?int $ignoreRequestId = null): int
    {
        return $this->totalDays() - $this->approvedDays($ignoreRequestId) - $this->reservedDays($ignoreRequestId);
    }

    public function isUsable(): bool
    {
        return $this->acquisition_ends_on->lt(today()) && (! $this->expires_on || $this->expires_on->gte(today()));
    }

    public function isRequestable(): bool
    {
        return ! $this->expires_on || $this->expires_on->gte(today());
    }

    private function requestDays(array $statuses, ?int $ignoreRequestId): int
    {
        if ($this->relationLoaded('requests')) {
            return $this->requests
                ->whereIn('status', $statuses)
                ->when($ignoreRequestId, fn ($requests) => $requests->where('id', '!=', $ignoreRequestId))
                ->sum(fn (VacationRequest $request) => $request->days());
        }

        return $this->requests()->whereIn('status', $statuses)
            ->when($ignoreRequestId, fn ($query) => $query->whereKeyNot($ignoreRequestId))
            ->get(['starts_on', 'ends_on'])
            ->sum(fn (VacationRequest $request) => $request->days());
    }
}
