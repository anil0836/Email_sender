<?php

namespace App\Services;

use App\Models\SalesforceContact;
use App\Models\SalesforceLead;
use App\Models\SalesforceMockRecord;
use App\Models\SalesforceSfUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalesforceOwnerService
{
    /**
     * Generic system roles and non-rep names that should not be used as sales rep owner names.
     */
    protected const SYSTEM_ROLES = [
        'standard sender',
        'sales manager',
        'purchase manager',
        'line manager',
        'admin user',
        'admin',
        'administrator',
        'system',
    ];

    /**
     * Dynamically resolve the Salesforce ownership criteria for a given User.
     *
     * @param User|null $user
     * @return array
     */
    public function resolveOwnerCriteria(?User $user): array
    {
        if (!$user) {
            return [
                'valid' => false,
                'owner_names' => [],
                'prime_owner_ids' => [],
                'sf_user_ids' => [],
                'primary_owner_name' => '',
            ];
        }

        $username = trim((string)($user->username ?? ''));
        $userEmail = strtolower(trim((string)($user->email ?? '')));
        $userName = trim((string)($user->name ?? ''));

        $ownerNames = [];
        $primeOwnerIds = [];
        $sfUserIds = [];

        // 1. Query SalesforceSfUser custom sales rep profile
        $sfUsers = SalesforceSfUser::query()
            ->where(function ($q) use ($userEmail, $username, $userName) {
                if (!empty($userEmail)) {
                    $q->whereRaw('LOWER(emp_email) = ?', [$userEmail]);
                }
                if (!empty($username)) {
                    $q->orWhereRaw('LOWER(name) = ?', [strtolower($username)])
                      ->orWhereRaw('LOWER(emp_name) = ?', [strtolower($username)]);
                }
                if (!empty($userName) && !in_array(strtolower($userName), self::SYSTEM_ROLES, true)) {
                    $q->orWhereRaw('LOWER(name) = ?', [strtolower($userName)])
                      ->orWhereRaw('LOWER(emp_name) = ?', [strtolower($userName)]);
                }
            })->get();

        foreach ($sfUsers as $sfu) {
            if (!empty($sfu->name)) {
                $ownerNames[] = trim($sfu->name);
            }
            if (!empty($sfu->emp_name) && !in_array(strtolower($sfu->emp_name), self::SYSTEM_ROLES, true)) {
                $ownerNames[] = trim($sfu->emp_name);
            }
            if (!empty($sfu->salesforce_id)) {
                $primeOwnerIds[] = trim($sfu->salesforce_id);
            }
            $sfUserIds[] = $sfu->id;
        }

        // 2. Direct username and user name matching
        if (!empty($username) && !in_array(strtolower($username), self::SYSTEM_ROLES, true)) {
            $ownerNames[] = ucfirst(strtolower($username));
            $ownerNames[] = $username;
        }
        if (!empty($userName) && !in_array(strtolower($userName), self::SYSTEM_ROLES, true)) {
            $ownerNames[] = $userName;
        }

        // Deduplicate and filter empty values
        $ownerNames = array_values(array_unique(array_filter($ownerNames)));
        $primeOwnerIds = array_values(array_unique(array_filter($primeOwnerIds)));
        $sfUserIds = array_values(array_unique(array_filter($sfUserIds)));

        $isValid = !empty($ownerNames) || !empty($primeOwnerIds) || !empty($sfUserIds);

        $primaryOwnerName = '';
        if (!empty($ownerNames)) {
            $primaryOwnerName = $ownerNames[0];
        } elseif (!empty($username)) {
            $primaryOwnerName = ucfirst(strtolower($username));
        }

        return [
            'valid' => $isValid,
            'owner_names' => $ownerNames,
            'prime_owner_ids' => $primeOwnerIds,
            'sf_user_ids' => $sfUserIds,
            'primary_owner_name' => $primaryOwnerName,
        ];
    }

    /**
     * Apply ownership filters to an Eloquent query for SalesforceLead, SalesforceContact, etc.
     *
     * @param Builder $query
     * @param User|null $user
     * @return Builder
     */
    public function applyOwnerFilter(Builder $query, ?User $user): Builder
    {
        $criteria = $this->resolveOwnerCriteria($user);
        if (!$criteria['valid']) {
            return $query->whereRaw('1 = 0');
        }

        $table = $query->getModel()->getTable();

        // Support for mock records table if used
        if ($table === 'salesforce_mock_records') {
            return $query->where(function (Builder $q) use ($criteria) {
                foreach ($criteria['owner_names'] as $name) {
                    $q->orWhereRaw('LOWER(owner_id) = ?', [strtolower($name)]);
                }
            });
        }

        $hasCustomOwnerLower = ($table === 'salesforce_leads');

        return $query->where(function (Builder $q) use ($criteria, $hasCustomOwnerLower) {
            // Match normalized owner name (case-insensitive) on owner_name and Custom_Owner__c
            if (!empty($criteria['owner_names'])) {
                $q->where(function (Builder $sub) use ($criteria, $hasCustomOwnerLower) {
                    foreach ($criteria['owner_names'] as $name) {
                        $lowerName = strtolower($name);
                        $sub->orWhereRaw('LOWER(owner_name) = ?', [$lowerName])
                            ->orWhereRaw('LOWER(Custom_Owner__c) = ?', [$lowerName]);
                        if ($hasCustomOwnerLower) {
                            $sub->orWhereRaw('LOWER(custom_owner) = ?', [$lowerName]);
                        }
                    }
                });
            }

            // Match local SalesforceSfUser primary key ID
            if (!empty($criteria['sf_user_ids'])) {
                $q->orWhereIn('salesforce_sf_user_id', $criteria['sf_user_ids']);
            }

            // Match Salesforce Prime Owner ID (Custom SF_User__c ID)
            if (!empty($criteria['prime_owner_ids'])) {
                $q->orWhereIn('prime_owner_id', $criteria['prime_owner_ids']);
            }
        });
    }

    /**
     * Check if a specific record belongs to the user.
     *
     * @param array|Model $record
     * @param User|null $user
     * @return bool
     */
    public function isRecordOwnedByUser(array|Model $record, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $criteria = $this->resolveOwnerCriteria($user);
        if (!$criteria['valid']) {
            return false;
        }

        $data = is_array($record) ? $record : $record->toArray();

        $ownerName = strtolower(trim((string)($data['owner_name'] ?? '')));
        $customOwner = strtolower(trim((string)($data['Custom_Owner__c'] ?? ($data['custom_owner'] ?? ''))));
        $salesforceSfUserId = $data['salesforce_sf_user_id'] ?? null;
        $primeOwnerId = trim((string)($data['prime_owner_id'] ?? ''));
        $ownerId = trim((string)($data['owner_id'] ?? ''));

        $userOwnerNames = array_map('strtolower', $criteria['owner_names']);

        // 1. If explicit Custom_Owner__c or owner_name is present:
        if (!empty($customOwner)) {
            return in_array($customOwner, $userOwnerNames, true);
        }

        if (!empty($ownerName)) {
            return in_array($ownerName, $userOwnerNames, true);
        }

        // 2. Local foreign key to salesforce_sf_users
        if (!empty($salesforceSfUserId) && in_array((int)$salesforceSfUserId, array_map('intval', $criteria['sf_user_ids']), true)) {
            return true;
        }

        // 3. Prime Owner SF ID
        if (!empty($primeOwnerId) && in_array($primeOwnerId, $criteria['prime_owner_ids'], true)) {
            return true;
        }

        // 4. Fallback owner_id (for mock records or standard SF user where username/name matches)
        if (!empty($ownerId)) {
            $lowerOwnerId = strtolower($ownerId);
            if (in_array($lowerOwnerId, $userOwnerNames, true)) {
                return true;
            }
        }

        return false;
    }
}
