<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'salesforce_client_id',
        'salesforce_client_secret',
        'salesforce_login_url',
        'salesforce_username',
        'salesforce_token_or_password',
        'is_mock',
    ];

    protected $casts = [
        'is_mock' => 'boolean',
    ];
}
