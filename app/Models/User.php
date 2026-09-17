<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

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
