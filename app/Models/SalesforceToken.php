<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesforceToken extends Model
{
    protected $table = 'salesforce_tokens';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'access_token',
        'instance_url',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
