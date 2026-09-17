<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesforceMockRecord extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'object_type',
        'first_name',
        'last_name',
        'email',
        'owner_id',
        'opted_out',
        'status',
        'consent_status',
        'lawful_basis',
        'do_not_call',
        'deal_category',
        'region',
        'country',
    ];

    protected $casts = [
        'opted_out' => 'boolean',
        'do_not_call' => 'boolean',
    ];
}
