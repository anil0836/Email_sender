<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SendingDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_name',
        'status',
        'spf_status',
        'dkim_status',
        'dmarc_status',
        'server_id',
        'is_default',
        'rate_limit_per_hour',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'rate_limit_per_hour' => 'integer',
        'server_id' => 'integer',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function assignedUsers()
    {
        return $this->hasMany(User::class, 'assigned_domain_id');
    }
}
