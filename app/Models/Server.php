<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'port',
        'username',
        'password',
        'sending_ip',
        'is_active',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
    ];

    public function sendingDomains()
    {
        return $this->hasMany(SendingDomain::class, 'server_id');
    }

    public function assignedUsers()
    {
        return $this->hasMany(User::class, 'assigned_server_id');
    }
}
