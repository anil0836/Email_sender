<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipientOpen extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipient_log_id',
        'opened_at',
        'ip_address',
        'user_agent',
        'country',
        'region',
        'city',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
    ];

    public function recipientLog()
    {
        return $this->belongsTo(RecipientLog::class, 'recipient_log_id');
    }
}
