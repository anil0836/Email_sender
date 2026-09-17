<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalSuppression extends Model
{
    use HasFactory;

    protected $table = 'global_suppression';

    protected $fillable = [
        'email',
        'reason',
        'added_at',
    ];

    protected $casts = [
        'added_at' => 'datetime',
    ];
}
