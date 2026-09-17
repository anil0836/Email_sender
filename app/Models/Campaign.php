<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'subject',
        'body',
        'sending_domain',
        'from_address',
        'reply_to',
        'user_id',
        'total_requested',
        'total_approved',
        'total_blocked',
        'status',
        'approved_by',
        'approval_remark',
        'approval_at',
        'scheduled_at',
        'attachments',
    ];

    protected $casts = [
        'total_requested' => 'integer',
        'total_approved' => 'integer',
        'total_blocked' => 'integer',
        'approval_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function recipientLogs()
    {
        return $this->hasMany(RecipientLog::class, 'campaign_id');
    }

    public function inboundReplies()
    {
        return $this->hasMany(InboundReply::class, 'campaign_id');
    }
}
