<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesforceCache extends Model
{
    use HasFactory;

    protected $table = 'salesforce_cache';
    protected $primaryKey = 'record_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'record_id',
        'object_type',
        'first_name',
        'last_name',
        'email',
        'owner_id',
        'opted_out',
        'status',
        'consent_status',
        'lawful_basis',
        'cached_at',
    ];

    protected $casts = [
        'opted_out' => 'boolean',
        'cached_at' => 'datetime',
    ];
}
