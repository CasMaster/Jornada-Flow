<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagerDelegation extends Model
{
    protected $fillable = ['manager_id', 'delegate_id', 'starts_on', 'ends_on', 'created_by'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date'];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }
}
