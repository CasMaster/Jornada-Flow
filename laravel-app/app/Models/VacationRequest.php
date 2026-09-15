<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationRequest extends Model
{
    protected $fillable = ['user_id', 'starts_on', 'ends_on', 'status', 'reviewed_by', 'reviewed_at', 'review_note', 'corrected_by', 'corrected_at', 'cancelled_by', 'cancelled_at', 'cancel_note'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'reviewed_at' => 'datetime', 'corrected_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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
