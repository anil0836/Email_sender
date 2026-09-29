<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesforceLead extends Model
{
    use HasFactory;

    protected $table = 'salesforce_leads';

    protected $fillable = [
        'salesforce_id',
        'first_name',
        'last_name',
        'name',
        'company',
        'title',
        'email',
        'phone',
        'mobile_phone',
        'website',
        'lead_source',
        'industry',
        'Deal_Category__c',
        'status',
        'is_converted',
        'street',
        'city',
        'state',
        'postal_code',
        'country',
        'owner_id',
        'prime_owner_id',
        'salesforce_sf_user_id',
        'secondary_owner',
        'custom_owner',
        'Custom_Owner__c',
        'owner_name',
        'owner_email',
        'owner_verification_status',
        'last_owner_verified_at',
        'previous_owner_id',
        'salesforce_created_at',
        'salesforce_updated_at',
        'synced_at',
        'raw_data',
    ];

    protected $casts = [
        'is_converted' => 'boolean',
        'salesforce_created_at' => 'datetime',
        'salesforce_updated_at' => 'datetime',
        'synced_at' => 'datetime',
        'last_owner_verified_at' => 'datetime',
        'raw_data' => 'array',
    ];

    /**
     * Relationship: Salesforce User who owns this Lead (Standard User)
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(SalesforceUser::class, 'owner_id', 'salesforce_id');
    }

    /**
     * Relationship: Custom Salesforce User (SF_User__c) who is Prime Owner
     */
    public function primeOwner(): BelongsTo
    {
        return $this->belongsTo(SalesforceSfUser::class, 'prime_owner_id', 'salesforce_id');
    }

    /**
     * Relationship: Matched local Salesforce SF User record (via Custom_Owner__c or local FK)
     */
    public function salesforceOwner(): BelongsTo
    {
        return $this->belongsTo(SalesforceSfUser::class, 'salesforce_sf_user_id');
    }

    /**
     * Relationship: Campaign Memberships for this Lead
     */
    public function campaignMembers()
    {
        return $this->hasMany(CampaignMember::class, 'local_record_id')->where('record_type', 'Lead');
    }

    /**
     * Accessor for deal_category / Deal_Category__c.
     */
    public function getDealCategoryAttribute(): ?string
    {
        return $this->attributes['Deal_Category__c'] ?? $this->attributes['industry'] ?? null;
    }

    /**
     * Scope to search leads across name, company, email, phone, deal category, and salesforce ID.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty(trim((string)$search))) {
            return $query;
        }

        $term = '%' . trim($search) . '%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', $term)
              ->orWhere('first_name', 'like', $term)
              ->orWhere('last_name', 'like', $term)
              ->orWhere('company', 'like', $term)
              ->orWhere('email', 'like', $term)
              ->orWhere('phone', 'like', $term)
              ->orWhere('mobile_phone', 'like', $term)
              ->orWhere('owner_name', 'like', $term)
              ->orWhere('owner_email', 'like', $term)
              ->orWhere('secondary_owner', 'like', $term)
              ->orWhere('custom_owner', 'like', $term)
              ->orWhere('Custom_Owner__c', 'like', $term)
              ->orWhere('Deal_Category__c', 'like', $term)
              ->orWhere('salesforce_id', 'like', $term);
        });
    }

    /**
     * Scope to filter by Deal Category (Deal_Category__c).
     */
    public function scopeFilterDealCategory(Builder $query, ?string $category): Builder
    {
        if (empty(trim((string)$category))) {
            return $query;
        }

        return $query->where('Deal_Category__c', trim($category));
    }

    /**
     * Scope to filter by Lead Status.
     */
    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        if (empty(trim((string)$status))) {
            return $query;
        }

        return $query->where('status', trim($status));
    }

    /**
     * Scope to filter by Lead Source.
     */
    public function scopeFilterLeadSource(Builder $query, ?string $source): Builder
    {
        if (empty(trim((string)$source))) {
            return $query;
        }

        return $query->where('lead_source', trim($source));
    }

    /**
     * Scope to filter by Standard User Owner.
     */
    public function scopeFilterOwner(Builder $query, ?string $ownerId): Builder
    {
        if (empty(trim((string)$ownerId))) {
            return $query;
        }

        return $query->where('owner_id', trim($ownerId));
    }

    /**
     * Scope to filter by Custom SF User Prime Owner (SF_User__c).
     */
    public function scopeFilterPrimeOwner(Builder $query, ?string $primeOwnerId): Builder
    {
        if (empty(trim((string)$primeOwnerId))) {
            return $query;
        }

        return $query->where('prime_owner_id', trim($primeOwnerId));
    }

    /**
     * Scope to filter by Owner Verification Status.
     */
    public function scopeFilterOwnerVerificationStatus(Builder $query, ?string $status): Builder
    {
        if (empty(trim((string)$status))) {
            return $query;
        }

        return $query->where('owner_verification_status', trim($status));
    }

    /**
     * Scope to filter by matched Salesforce SF User ID.
     */
    public function scopeFilterSalesforceSfUser(Builder $query, ?int $sfUserId): Builder
    {
        if (empty($sfUserId)) {
            return $query;
        }

        return $query->where('salesforce_sf_user_id', $sfUserId);
    }

    /**
     * Scope to filter by Custom Owner name.
     */
    public function scopeFilterCustomOwner(Builder $query, ?string $customOwner): Builder
    {
        if (empty(trim((string)$customOwner))) {
            return $query;
        }

        $val = trim($customOwner);
        return $query->where(function ($q) use ($val) {
            $q->where('custom_owner', $val)
              ->orWhere('Custom_Owner__c', $val);
        });
    }

    /**
     * Scope to filter only unconverted leads (IsConverted = false).
     */
    public function scopeUnconverted(Builder $query): Builder
    {
        return $query->where('is_converted', false);
    }
}
