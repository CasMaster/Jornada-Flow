<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationRequest extends Model
{
    protected $fillable = ['user_id', 'vacation_entitlement_id', 'request_type', 'starts_on', 'ends_on', 'cash_allowance_days', 'status', 'reviewed_by', 'reviewed_at', 'review_note', 'corrected_by', 'corrected_at', 'cancelled_by', 'cancelled_at', 'cancel_note'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'cash_allowance_days' => 'integer', 'reviewed_at' => 'datetime', 'corrected_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(VacationEntitlement::class, 'vacation_entitlement_id');
    }

    public function days(): int
    {
        return $this->isAllowanceOnly() ? 0 : $this->starts_on->diffInDays($this->ends_on) + 1;
    }

    public function isAllowanceOnly(): bool
    {
        return $this->request_type === 'cash_allowance';
    }

    public function totalDebitedDays(): int
    {
        return $this->days() + $this->cash_allowance_days;
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
