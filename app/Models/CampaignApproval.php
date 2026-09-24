<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignApproval extends Model
{
    use HasFactory;

    protected $table = 'campaign_approvals';

    protected $fillable = [
        'campaign_id',
        'approver_id',
        'approver_role',
        'action',
        'previous_status',
        'new_status',
        'comment',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
