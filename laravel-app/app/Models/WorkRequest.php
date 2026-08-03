<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WorkRequest extends Model
{
    protected $fillable = ['user_id', 'work_date', 'status', 'reviewed_by', 'reviewed_at'];
    protected $casts = ['work_date' => 'date', 'reviewed_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
