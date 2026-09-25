<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'emp_id',
        'username',
        'name',
        'email',
        'password',
        'role',
        'manager_id',
        'assigned_server_id',
        'assigned_domain_id',
        'is_blocked',
        'daily_limit',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_blocked' => 'boolean',
            'daily_limit' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (User $user) {
            $user->syncSpatieRoleFromColumn();
        });

        static::saved(function (User $user) {
            if ($user->wasChanged('role')) {
                $user->syncSpatieRoleFromColumn();
            }
        });
    }

    /**
     * Map and synchronize legacy role column to Spatie roles.
     * Maps 'user' or 'employee' to 'Employee', 'manager' to 'Manager', and 'admin' to 'Admin'.
     */
    public function syncSpatieRoleFromColumn(): void
    {
        try {
            $role = strtolower(trim((string) $this->role));
            $targetRole = match ($role) {
                'admin' => 'Admin',
                'manager' => 'Manager',
                'line_manager' => 'Line Manager',
                'user', 'employee' => 'Employee',
                default => !empty($role) ? ucfirst($role) : 'Employee',
            };

            if (\Spatie\Permission\Models\Role::where('name', $targetRole)->where('guard_name', 'web')->exists()) {
                if (!$this->hasRole($targetRole)) {
                    $this->assignRole($targetRole);
                }
            }
        } catch (\Throwable $e) {
            // Ignore during early migrations or if permission tables are not ready
        }
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function directReports()
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function assignedServer()
    {
        return $this->belongsTo(Server::class, 'assigned_server_id');
    }

    public function assignedDomain()
    {
        return $this->belongsTo(SendingDomain::class, 'assigned_domain_id');
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class, 'user_id');
    }

    public function managedCampaigns()
    {
        return $this->hasMany(Campaign::class, 'manager_user_id');
    }

    public function isTeamManager(): bool
    {
        return app(\App\Services\TeamService::class)->isTeamManager($this);
    }

    public function managedTeams(): array
    {
        return app(\App\Services\TeamService::class)->getManagedTeamsForUser($this);
    }

    public function getTeamAttribute(): ?string
    {
        return app(\App\Services\TeamService::class)->resolveUserTeam($this);
    }

    public function signatures()
    {
        return $this->hasMany(UserSignature::class, 'user_id');
    }

    public function templates()
    {
        return $this->hasMany(CampaignTemplate::class, 'user_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }
}
