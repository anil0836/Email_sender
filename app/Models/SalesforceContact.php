<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesforceContact extends Model
{
    use HasFactory;

    protected $table = 'salesforce_contacts';

    protected $fillable = [
        'salesforce_id',
        'account_id',
        'first_name',
        'last_name',
        'name',
        'title',
        'department',
        'email',
        'phone',
        'mobile_phone',
        'lead_source',
        'mailing_street',
        'mailing_city',
        'mailing_state',
        'mailing_postal_code',
        'mailing_country',
        'owner_id',
        'prime_owner_id',
        'secondary_owner',
        'Custom_Owner__c',
        'custom_owner',
        'salesforce_sf_user_id',
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
        'salesforce_created_at' => 'datetime',
        'salesforce_updated_at' => 'datetime',
        'synced_at' => 'datetime',
        'last_owner_verified_at' => 'datetime',
        'raw_data' => 'array',
    ];

    /**
     * Relationship: Salesforce Account this contact belongs to
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SalesforceAccount::class, 'account_id', 'salesforce_id');
    }

    /**
     * Relationship: Salesforce User who owns this Contact (Standard User)
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
     * Accessor for custom_owner to map cleanly to Custom_Owner__c
     */
    public function getCustomOwnerAttribute(): ?string
    {
        return $this->attributes['Custom_Owner__c'] ?? null;
    }

    /**
     * Mutator for custom_owner to map cleanly to Custom_Owner__c
     */
    public function setCustomOwnerAttribute(?string $value): void
    {
        $this->attributes['Custom_Owner__c'] = $value;
    }

    /**
     * Relationship: Campaign Memberships for this Contact
     */
    public function campaignMembers()
    {
        return $this->hasMany(CampaignMember::class, 'local_record_id')->where('record_type', 'Contact');
    }

    /**
     * Scope to search contacts by name, email, phone, title, department, or salesforce_id.
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
              ->orWhere('email', 'like', $term)
              ->orWhere('phone', 'like', $term)
              ->orWhere('mobile_phone', 'like', $term)
              ->orWhere('title', 'like', $term)
              ->orWhere('department', 'like', $term)
              ->orWhere('mailing_city', 'like', $term)
              ->orWhere('mailing_country', 'like', $term)
              ->orWhere('secondary_owner', 'like', $term)
              ->orWhere('salesforce_id', 'like', $term);
        });
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

        return $query->where('Custom_Owner__c', trim($customOwner));
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
}

