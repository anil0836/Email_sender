<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesforceAccount extends Model
{
    use HasFactory;

    protected $table = 'salesforce_accounts';

    protected $fillable = [
        'salesforce_id',
        'name',
        'type',
        'industry',
        'phone',
        'website',
        'billing_street',
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'billing_country',
        'shipping_street',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'number_of_employees',
        'owner_id',
        'prime_owner_id',
        'secondary_owner',
        'custom_owner',
        'owner_name',
        'owner_email',
        'owner_verification_status',
        'last_owner_verified_at',
        'previous_owner_id',
        'parent_id',
        'salesforce_created_at',
        'salesforce_updated_at',
        'synced_at',
        'raw_data',
    ];

    protected $casts = [
        'number_of_employees' => 'integer',
        'salesforce_created_at' => 'datetime',
        'salesforce_updated_at' => 'datetime',
        'synced_at' => 'datetime',
        'last_owner_verified_at' => 'datetime',
        'raw_data' => 'array',
    ];

    /**
     * Relationship: Contacts associated with this Account
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(SalesforceContact::class, 'account_id', 'salesforce_id');
    }

    /**
     * Relationship: Salesforce User who owns this Account (Standard User)
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
     * Relationship: Campaign Memberships for this Account
     */
    public function campaignMembers()
    {
        return $this->hasMany(CampaignMember::class, 'local_record_id')->where('record_type', 'Account');
    }

    /**
     * Scope to search accounts by name, phone, website, industry, city, or salesforce_id.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty(trim((string)$search))) {
            return $query;
        }

        $term = '%' . trim($search) . '%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', $term)
              ->orWhere('type', 'like', $term)
              ->orWhere('industry', 'like', $term)
              ->orWhere('phone', 'like', $term)
              ->orWhere('website', 'like', $term)
              ->orWhere('billing_city', 'like', $term)
              ->orWhere('billing_country', 'like', $term)
              ->orWhere('secondary_owner', 'like', $term)
              ->orWhere('custom_owner', 'like', $term)
              ->orWhere('salesforce_id', 'like', $term);
        });
    }

    /**
     * Scope to filter by Account Type.
     */
    public function scopeFilterType(Builder $query, ?string $type): Builder
    {
        if (empty(trim((string)$type))) {
            return $query;
        }

        return $query->where('type', trim($type));
    }

    /**
     * Scope to filter by Industry.
     */
    public function scopeFilterIndustry(Builder $query, ?string $industry): Builder
    {
        if (empty(trim((string)$industry))) {
            return $query;
        }

        return $query->where('industry', trim($industry));
    }
}
