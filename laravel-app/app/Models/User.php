<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password', 'role', 'team', 'hired_on', 'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'hired_on' => 'date',
            'auth_version' => 'integer',
        ];
    }

    public function managedTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'manager_team', 'manager_id', 'team_id')->withTimestamps();
    }

    public function workRequests(): HasMany
    {
        return $this->hasMany(WorkRequest::class);
    }

    public function vacationRequests(): HasMany
    {
        return $this->hasMany(VacationRequest::class);
    }

    public function vacationEntitlements(): HasMany
    {
        return $this->hasMany(VacationEntitlement::class);
    }

    public function receivedDelegations(): HasMany
    {
        return $this->hasMany(ManagerDelegation::class, 'delegate_id');
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['manager', 'super_admin'], true);
    }
}
