<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Team extends Model
{
    protected $fillable = ['name', 'active'];
    protected $casts = ['active' => 'boolean'];
    public function managers(): BelongsToMany { return $this->belongsToMany(User::class, 'manager_team', 'team_id', 'manager_id')->withTimestamps(); }
}
