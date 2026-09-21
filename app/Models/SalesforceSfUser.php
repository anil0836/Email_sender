<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesforceSfUser extends Model
{
    use HasFactory;

    protected $table = 'salesforce_sf_users';

    protected $fillable = [
        'salesforce_id',
        'name',
        'emp_name',
        'full_name_in',
        'emp_email',
        'emp_code',
        'process',
        'team',
        'location',
        'is_active',
        'doj',
        'dol',
        'dob',
        'phone',
        'mobile',
        'linkedin',
        'owner_id',
        'salesforce_created_at',
        'salesforce_updated_at',
        'synced_at',
        'raw_data',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'doj' => 'date',
        'dol' => 'date',
        'dob' => 'date',
        'salesforce_created_at' => 'datetime',
        'salesforce_updated_at' => 'datetime',
        'synced_at' => 'datetime',
        'raw_data' => 'array',
    ];

    /**
     * Relationship: Standard Salesforce User account owning this record
     */
    public function standardUser(): BelongsTo
    {
        return $this->belongsTo(SalesforceUser::class, 'owner_id', 'salesforce_id');
    }

    /**
     * Relationship: Team Manager in SalesforceUser where first_name = team
     */
    public function teamManager(): BelongsTo
    {
        return $this->belongsTo(SalesforceUser::class, 'team', 'first_name');
    }

    /**
     * Relationship: Leads where this SF User is Prime Owner (Prime_Owner__c)
     */
    public function primeLeads(): HasMany
    {
        return $this->hasMany(SalesforceLead::class, 'prime_owner_id', 'salesforce_id');
    }

    /**
     * Relationship: Accounts where this SF User is Prime Owner (Prime_Owner__c)
     */
    public function primeAccounts(): HasMany
    {
        return $this->hasMany(SalesforceAccount::class, 'prime_owner_id', 'salesforce_id');
    }

    /**
     * Relationship: Contacts where this SF User is Prime Owner (Prime_Owner__c)
     */
    public function primeContacts(): HasMany
    {
        return $this->hasMany(SalesforceContact::class, 'prime_owner_id', 'salesforce_id');
    }

    /**
     * Relationship: Leads assigned to this SF User (via salesforce_sf_user_id)
     */
    public function leads(): HasMany
    {
        return $this->hasMany(SalesforceLead::class, 'salesforce_sf_user_id');
    }

    /**
     * Scope to search SF Users by name, emp_name, full_name_in, email, emp_code, process, or salesforce_id.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty(trim((string)$search))) {
            return $query;
        }

        $term = '%' . trim($search) . '%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', $term)
              ->orWhere('emp_name', 'like', $term)
              ->orWhere('full_name_in', 'like', $term)
              ->orWhere('emp_email', 'like', $term)
              ->orWhere('emp_code', 'like', $term)
              ->orWhere('process', 'like', $term)
              ->orWhere('team', 'like', $term)
              ->orWhere('location', 'like', $term)
              ->orWhere('salesforce_id', 'like', $term);
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
     * Scope to filter by Department / Process.
     */
    public function scopeFilterProcess(Builder $query, ?string $process): Builder
    {
        if (empty(trim((string)$process))) {
            return $query;
        }

        return $query->where('process', trim($process));
    }

    /**
     * Scope to filter by Team.
     */
    public function scopeFilterTeam(Builder $query, ?string $team): Builder
    {
        if (empty(trim((string)$team))) {
            return $query;
        }

        return $query->where('team', trim($team));
    }

    /**
     * Scope to filter by Location.
     */
    public function scopeFilterLocation(Builder $query, ?string $location): Builder
    {
        if (empty(trim((string)$location))) {
            return $query;
        }

        return $query->where('location', trim($location));
    }
}
