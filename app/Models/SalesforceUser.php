<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesforceUser extends Model
{
    use HasFactory;

    protected $table = 'salesforce_users';

    protected $fillable = [
        'salesforce_id',
        'username',
        'first_name',
        'last_name',
        'name',
        'email',
        'title',
        'department',
        'company_name',
        'division',
        'phone',
        'mobile_phone',
        'city',
        'state',
        'country',
        'is_active',
        'user_type',
        'profile_id',
        'user_role_id',
        'manager_id',
        'salesforce_created_at',
        'salesforce_updated_at',
        'synced_at',
        'raw_data',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'salesforce_created_at' => 'datetime',
        'salesforce_updated_at' => 'datetime',
        'synced_at' => 'datetime',
        'raw_data' => 'array',
    ];

    /**
     * Relationship: Manager of this Salesforce User (Salesforce User.ManagerId -> Salesforce User.Id)
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(SalesforceUser::class, 'manager_id', 'salesforce_id');
    }

    /**
     * Relationship: Direct reports of this Salesforce User
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(SalesforceUser::class, 'manager_id', 'salesforce_id');
    }

    /**
     * Relationship: Salesforce Leads owned by this user
     */
    public function leads(): HasMany
    {
        return $this->hasMany(SalesforceLead::class, 'owner_id', 'salesforce_id');
    }

    /**
     * Relationship: Salesforce Accounts owned by this user
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(SalesforceAccount::class, 'owner_id', 'salesforce_id');
    }

    /**
     * Relationship: Salesforce Contacts owned by this user
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(SalesforceContact::class, 'owner_id', 'salesforce_id');
    }

    /**
     * Relationship: Salesforce Custom Users (SF_User__c) created/owned by this user
     */
    public function sfUsers(): HasMany
    {
        return $this->hasMany(SalesforceSfUser::class, 'owner_id', 'salesforce_id');
    }

    /**
     * Scope to search users by name, username, email, department, title, or salesforce_id.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty(trim((string)$search))) {
            return $query;
        }

        $term = '%' . trim($search) . '%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', $term)
              ->orWhere('username', 'like', $term)
              ->orWhere('email', 'like', $term)
              ->orWhere('title', 'like', $term)
              ->orWhere('department', 'like', $term)
              ->orWhere('company_name', 'like', $term)
              ->orWhere('salesforce_id', 'like', $term)
              ->orWhere('manager_id', 'like', $term);
        });
    }

    /**
     * Scope to filter by Active status.
     */
    public function scopeFilterActive(Builder $query, $active): Builder
    {
        if ($active === null || $active === '') {
            return $query;
        }

        return $query->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * Scope to filter by User Type.
     */
    public function scopeFilterUserType(Builder $query, ?string $userType): Builder
    {
        if (empty(trim((string)$userType))) {
            return $query;
        }

        return $query->where('user_type', trim($userType));
    }
}
