<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipientClick extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipient_log_id',
        'clicked_at',
        'url',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    public function recipientLog()
    {
        return $this->belongsTo(RecipientLog::class, 'recipient_log_id');
    }
}
