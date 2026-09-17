<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InboundReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'recipient_email',
        'reply_subject',
        'reply_body',
        'received_at',
        'mapped_salesforce_record_id',
        'mapped_owner_id',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
}
