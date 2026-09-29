<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipientLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'campaign_member_id',
        'email',
        'salesforce_record_id',
        'salesforce_object',
        'record_owner_id',
        'owner_verification_status',
        'decision',
        'decision_reason',
        'delivery_status',
        'provider_message_id',
        'error_message',
        'country',
        'region',
        'city',
        'validated_at',
        'sent_at',
        'tracking_token',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function campaignMember()
    {
        return $this->belongsTo(CampaignMember::class, 'campaign_member_id');
    }

    public function opens()
    {
        return $this->hasMany(RecipientOpen::class, 'recipient_log_id');
    }

    public function clicks()
    {
        return $this->hasMany(RecipientClick::class, 'recipient_log_id');
    }
}
