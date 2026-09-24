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
        'template_id',
        'signature_id',
        'signature_snapshot',
        'team',
        'manager_salesforce_id',
        'manager_user_id',
        'total_requested',
        'total_approved',
        'total_blocked',
        'status',
        'current_approver_id',
        'line_manager_id',
        'approved_by',
        'approval_remark',
        'approval_at',
        'rejected_by',
        'rejection_reason',
        'rejected_at',
        'scheduled_at',
        'attachments',
    ];

    protected $casts = [
        'total_requested' => 'integer',
        'total_approved' => 'integer',
        'total_blocked' => 'integer',
        'approval_at' => 'datetime',
        'rejected_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function template()
    {
        return $this->belongsTo(CampaignTemplate::class, 'template_id');
    }

    public function signature()
    {
        return $this->belongsTo(UserSignature::class, 'signature_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function salesforceManager()
    {
        return $this->belongsTo(SalesforceUser::class, 'manager_salesforce_id', 'salesforce_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function currentApprover()
    {
        return $this->belongsTo(User::class, 'current_approver_id');
    }

    public function lineManager()
    {
        return $this->belongsTo(User::class, 'line_manager_id');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function approvals()
    {
        return $this->hasMany(CampaignApproval::class, 'campaign_id')->orderBy('created_at', 'asc');
    }

    public function recipientLogs()
    {
        return $this->hasMany(RecipientLog::class, 'campaign_id');
    }

    public function members()
    {
        return $this->hasMany(CampaignMember::class, 'campaign_id');
    }

    public function leadMembers()
    {
        return $this->hasMany(CampaignMember::class, 'campaign_id')->where('record_type', 'Lead');
    }

    public function contactMembers()
    {
        return $this->hasMany(CampaignMember::class, 'campaign_id')->where('record_type', 'Contact');
    }

    public function accountMembers()
    {
        return $this->hasMany(CampaignMember::class, 'campaign_id')->where('record_type', 'Account');
    }

    public function inboundReplies()
    {
        return $this->hasMany(InboundReply::class, 'campaign_id');
    }

    /**
     * Checks if this campaign is fully approved and eligible for email sending.
     */
    public function isFullyApproved(): bool
    {
        if (in_array($this->status, ['draft', 'pending_approval', 'pending_line_manager', 'pending_manager', 'rejected', 'failed'])) {
            return false;
        }

        return in_array($this->status, ['approved', 'queued', 'sending', 'scheduled', 'completed']);
    }

    /**
     * Scope: Restrict query to Delivery Campaigns accessible by a given user.
     * Admin -> All campaigns
     * Line Manager / Manager -> Own campaigns + Subordinates' campaigns + Campaigns awaiting their approval
     * Normal User -> Own campaigns only
     */
    public function scopeAccessibleBy($query, User|int|string|null $user)
    {
        if (!$user) {
            return $query->whereRaw('0 = 1');
        }

        $localUser = $user instanceof User ? $user : User::find($user);
        if (!$localUser) {
            return $query->whereRaw('0 = 1');
        }

        if ($localUser->role === 'admin') {
            return $query;
        }

        $teamService = app(\App\Services\TeamService::class);
        $managedTeams = $teamService->getManagedTeamsForUser($localUser);
        $subordinateIds = User::where('manager_id', $localUser->id)->pluck('id')->toArray();

        // Also check recursive subordinates if manager
        if ($localUser->role === 'manager') {
            $lineManagerIds = User::where('manager_id', $localUser->id)->where('role', 'line_manager')->pluck('id')->toArray();
            if (!empty($lineManagerIds)) {
                $subSubIds = User::whereIn('manager_id', $lineManagerIds)->pluck('id')->toArray();
                $subordinateIds = array_unique(array_merge($subordinateIds, $subSubIds));
            }
        }

        if (count($managedTeams) > 0 || count($subordinateIds) > 0 || in_array($localUser->role, ['manager', 'line_manager'])) {
            return $query->where(function ($q) use ($localUser, $managedTeams, $subordinateIds) {
                $q->where('campaigns.user_id', $localUser->id)
                  ->orWhere('campaigns.manager_user_id', $localUser->id)
                  ->orWhere('campaigns.current_approver_id', $localUser->id)
                  ->orWhere('campaigns.line_manager_id', $localUser->id);

                if (count($managedTeams) > 0) {
                    $q->orWhereIn('campaigns.team', $managedTeams);
                }

                if (count($subordinateIds) > 0) {
                    $q->orWhereIn('campaigns.user_id', $subordinateIds);
                }
            });
        }

        return $query->where('campaigns.user_id', $localUser->id);
    }

    /**
     * Scope: Filter by Team.
     */
    public function scopeForTeam($query, ?string $team)
    {
        if (!empty($team)) {
            return $query->where('campaigns.team', $team);
        }
        return $query;
    }
}
