<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'record_type',
        'local_record_id',
        'salesforce_record_id',
        'email',
        'name',
        'company',
        'salesforce_owner_id',
        'prime_owner_id',
        'owner_name',
        'owner_email',
        'owner_verification_status',
        'last_owner_verified_at',
        'status',
    ];

    protected $casts = [
        'last_owner_verified_at' => 'datetime',
    ];

    /**
     * Parent campaign.
     */
    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * Linked Salesforce Lead (if record_type === 'Lead').
     */
    public function lead()
    {
        return $this->belongsTo(SalesforceLead::class, 'local_record_id');
    }

    /**
     * Linked Salesforce Contact (if record_type === 'Contact').
     */
    public function contact()
    {
        return $this->belongsTo(SalesforceContact::class, 'local_record_id');
    }

    /**
     * Linked Salesforce Account (if record_type === 'Account').
     */
    public function account()
    {
        return $this->belongsTo(SalesforceAccount::class, 'local_record_id');
    }

    /**
     * Standard Salesforce User owner.
     */
    public function owner()
    {
        return $this->belongsTo(SalesforceUser::class, 'salesforce_owner_id', 'salesforce_id');
    }

    /**
     * Custom SF_User__c Prime Owner.
     */
    public function primeOwner()
    {
        return $this->belongsTo(SalesforceSfUser::class, 'prime_owner_id', 'salesforce_id');
    }

    /**
     * Dynamic getter to retrieve underlying Salesforce entity.
     */
    public function getRecordAttribute(): ?Model
    {
        return match ($this->record_type) {
            'Lead' => $this->lead,
            'Contact' => $this->contact,
            'Account' => $this->account,
            default => null,
        };
    }

    /**
     * Scope by record type.
     */
    public function scopeType($query, string $type)
    {
        return $query->where('record_type', $type);
    }

    /**
     * Scope by owner verification status.
     */
    public function scopeVerificationStatus($query, string $status)
    {
        return $query->where('owner_verification_status', $status);
    }
}
