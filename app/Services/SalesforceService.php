<?php

namespace App\Services;

use App\Models\GlobalSuppression;
use App\Models\SalesforceAccount;
use App\Models\SalesforceCache;
use App\Models\SalesforceContact;
use App\Models\SalesforceLead;
use App\Models\SalesforceMockRecord;
use App\Models\SalesforceSfUser;
use App\Models\SalesforceUser;
use App\Models\User;
use Carbon\Carbon;

class SalesforceService
{
    /**
     * Simulates returning the OAuth Authorization URL.
     */
    public function getOAuthUrl(): string
    {
        return "https://login.salesforce.com/services/oauth2/authorize?client_id=mock_client_id&redirect_uri=" . urlencode(url('/api/salesforce/callback')) . "&response_type=code";
    }

    /**
     * Simulates exchanging authorization code for access/refresh tokens.
     */
    public function performOAuthCallback(string $code): array
    {
        return [
            'access_token' => 'mock_access_token_' . $code,
            'refresh_token' => 'mock_refresh_token',
            'instance_url' => 'https://mockinstance.salesforce.com',
            'issued_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Retrieves a Salesforce record from local tables (Lead, Contact, Account).
     */
    public function getSalesforceRecord(string $recordId): ?array
    {
        // 1. Check in salesforce_leads
        $lead = SalesforceLead::with(['owner', 'primeOwner'])
            ->where('salesforce_id', $recordId)
            ->orWhere('id', is_numeric($recordId) ? (int)$recordId : -1)
            ->first();

        if ($lead) {
            return $this->formatLeadRecord($lead);
        }

        // 2. Check in salesforce_contacts
        $contact = SalesforceContact::with(['owner', 'primeOwner', 'account'])
            ->where('salesforce_id', $recordId)
            ->orWhere('id', is_numeric($recordId) ? (int)$recordId : -1)
            ->first();

        if ($contact) {
            return $this->formatContactRecord($contact);
        }

        // 3. Check in salesforce_accounts
        $account = SalesforceAccount::with(['owner', 'primeOwner'])
            ->where('salesforce_id', $recordId)
            ->orWhere('id', is_numeric($recordId) ? (int)$recordId : -1)
            ->first();

        if ($account) {
            return $this->formatAccountRecord($account);
        }

        // 4. Fallback to mock table for historical compatibility if present
        $mock = SalesforceMockRecord::find($recordId);
        if ($mock) {
            $data = $mock->toArray();
            $data['local_id'] = null;
            return $data;
        }

        return null;
    }

    /**
     * Retrieves a Salesforce record by email from local tables.
     */
    public function getSalesforceRecordByEmail(string $email): ?array
    {
        $cleanEmail = trim($email);

        // 1. Check in salesforce_leads
        $lead = SalesforceLead::with(['owner', 'primeOwner'])
            ->where('email', $cleanEmail)
            ->first();

        if ($lead) {
            return $this->formatLeadRecord($lead);
        }

        // 2. Check in salesforce_contacts
        $contact = SalesforceContact::with(['owner', 'primeOwner', 'account'])
            ->where('email', $cleanEmail)
            ->first();

        if ($contact) {
            return $this->formatContactRecord($contact);
        }

        // 3. Fallback to mock table
        $mock = SalesforceMockRecord::where('email', $cleanEmail)->first();
        if ($mock) {
            $data = $mock->toArray();
            $data['local_id'] = null;
            return $data;
        }

        return null;
    }

    /**
     * Format a SalesforceLead model to normalized record array.
     */
    public function formatLeadRecord(SalesforceLead $lead): array
    {
        $ownerName = $lead->owner_name ?: ($lead->owner ? $lead->owner->name : ($lead->primeOwner ? $lead->primeOwner->name : ''));
        $ownerEmail = $lead->owner_email ?: ($lead->owner ? $lead->owner->email : ($lead->primeOwner ? $lead->primeOwner->emp_email : ''));

        return [
            'id' => $lead->salesforce_id,
            'local_id' => $lead->id,
            'object_type' => 'Lead',
            'first_name' => $lead->first_name ?: '',
            'last_name' => $lead->last_name ?: '',
            'name' => $lead->name ?: trim($lead->first_name . ' ' . $lead->last_name),
            'email' => $lead->email ?: '',
            'company' => $lead->company ?: '',
            'phone' => $lead->phone ?: $lead->mobile_phone,
            'owner_id' => $lead->owner_id ?: ($lead->prime_owner_id ?: ''),
            'salesforce_owner_id' => $lead->owner_id,
            'prime_owner_id' => $lead->prime_owner_id,
            'owner_name' => $ownerName,
            'owner_email' => $ownerEmail,
            'owner_verification_status' => $lead->owner_verification_status ?: 'verified',
            'last_owner_verified_at' => $lead->last_owner_verified_at ? $lead->last_owner_verified_at->toIso8601String() : null,
            'opted_out' => false,
            'status' => $lead->status ?: 'New',
            'consent_status' => 'valid',
            'lawful_basis' => 'Legitimate Interest',
            'deal_category' => $lead->industry ?: 'General',
            'city' => $lead->city ?: '',
            'region' => $lead->state ?: 'Global',
            'country' => $lead->country ?: '',
        ];
    }

    /**
     * Format a SalesforceContact model to normalized record array.
     */
    public function formatContactRecord(SalesforceContact $contact): array
    {
        $ownerName = $contact->owner_name ?: ($contact->owner ? $contact->owner->name : ($contact->primeOwner ? $contact->primeOwner->name : ''));
        $ownerEmail = $contact->owner_email ?: ($contact->owner ? $contact->owner->email : ($contact->primeOwner ? $contact->primeOwner->emp_email : ''));
        $company = $contact->account ? $contact->account->name : '';

        return [
            'id' => $contact->salesforce_id,
            'local_id' => $contact->id,
            'object_type' => 'Contact',
            'first_name' => $contact->first_name ?: '',
            'last_name' => $contact->last_name ?: '',
            'name' => $contact->name ?: trim($contact->first_name . ' ' . $contact->last_name),
            'email' => $contact->email ?: '',
            'company' => $company,
            'phone' => $contact->phone ?: $contact->mobile_phone,
            'owner_id' => $contact->owner_id ?: ($contact->prime_owner_id ?: ''),
            'salesforce_owner_id' => $contact->owner_id,
            'prime_owner_id' => $contact->prime_owner_id,
            'owner_name' => $ownerName,
            'owner_email' => $ownerEmail,
            'owner_verification_status' => $contact->owner_verification_status ?: 'verified',
            'last_owner_verified_at' => $contact->last_owner_verified_at ? $contact->last_owner_verified_at->toIso8601String() : null,
            'opted_out' => false,
            'status' => 'Active',
            'consent_status' => 'valid',
            'lawful_basis' => 'Consent',
            'deal_category' => $contact->department ?: 'General',
            'city' => $contact->mailing_city ?: '',
            'region' => $contact->mailing_state ?: 'Global',
            'country' => $contact->mailing_country ?: '',
        ];
    }

    /**
     * Format a SalesforceAccount model to normalized record array.
     */
    public function formatAccountRecord(SalesforceAccount $account): array
    {
        $ownerName = $account->owner_name ?: ($account->owner ? $account->owner->name : ($account->primeOwner ? $account->primeOwner->name : ''));
        $ownerEmail = $account->owner_email ?: ($account->owner ? $account->owner->email : ($account->primeOwner ? $account->primeOwner->emp_email : ''));

        return [
            'id' => $account->salesforce_id,
            'local_id' => $account->id,
            'object_type' => 'Account',
            'first_name' => '',
            'last_name' => '',
            'name' => $account->name ?: '',
            'email' => '',
            'company' => $account->name ?: '',
            'phone' => $account->phone ?: '',
            'owner_id' => $account->owner_id ?: ($account->prime_owner_id ?: ''),
            'salesforce_owner_id' => $account->owner_id,
            'prime_owner_id' => $account->prime_owner_id,
            'owner_name' => $ownerName,
            'owner_email' => $ownerEmail,
            'owner_verification_status' => $account->owner_verification_status ?: 'verified',
            'last_owner_verified_at' => $account->last_owner_verified_at ? $account->last_owner_verified_at->toIso8601String() : null,
            'opted_out' => false,
            'status' => $account->type ?: 'Active',
            'consent_status' => 'valid',
            'lawful_basis' => 'Legitimate Interest',
            'deal_category' => $account->industry ?: 'General',
            'region' => $account->billing_state ?: 'Global',
            'country' => $account->billing_country ?: '',
        ];
    }

    /**
     * Validates recipient eligibility at recipient ID level.
     * Returns [$isApproved, $decisionReason, $recordDetails]
     */
    public function checkRecipientEligibility(string $recordId, string $appUsername): array
    {
        $record = $this->getSalesforceRecord($recordId);
        if (!$record) {
            return [false, 'INVALID_EMAIL', null];
        }

        return $this->evaluateRecordRules($record, $appUsername);
    }

    /**
     * Validates recipient eligibility at email address level.
     * Returns [$isApproved, $decisionReason, $recordDetails]
     */
    public function checkRecipientEligibilityByEmail(string $email, string $appUsername): array
    {
        $record = $this->getSalesforceRecordByEmail($email);
        if (!$record) {
            return [false, 'INVALID_EMAIL', null];
        }

        return $this->evaluateRecordRules($record, $appUsername);
    }

    /**
     * Evaluates compliance and ownership rules against a record.
     */
    protected function evaluateRecordRules(array $record, string $appUsername): array
    {
        $email = $record['email'] ?? '';

        // 1. Ownership validation
        $isOwnerMatch = false;
        $currentUser = User::where('username', $appUsername)->orWhere('email', $appUsername)->first();
        $ownerId = $record['owner_id'] ?? '';
        $primeOwnerId = $record['prime_owner_id'] ?? '';
        $ownerEmail = $record['owner_email'] ?? '';

        if ($ownerId === $appUsername || $ownerEmail === $appUsername) {
            $isOwnerMatch = true;
        } elseif ($currentUser) {
            if ($ownerId === $currentUser->username || $ownerEmail === $currentUser->email) {
                $isOwnerMatch = true;
            }
            // Check if mapped to a SalesforceUser standard ID
            $sfUser = SalesforceUser::where('salesforce_id', $ownerId)->first();
            if ($sfUser && ($sfUser->username === $currentUser->username || $sfUser->email === $currentUser->email)) {
                $isOwnerMatch = true;
            }
            // Check if mapped to a SalesforceSfUser custom prime owner
            $sfCustom = SalesforceSfUser::where('salesforce_id', $primeOwnerId)->first();
            if ($sfCustom && ($sfCustom->emp_email === $currentUser->email || $sfCustom->emp_code === $currentUser->emp_id)) {
                $isOwnerMatch = true;
            }
        }

        if (!$isOwnerMatch) {
            return [false, 'DIFFERENT_OWNER', $record];
        }

        // 2. Email Opt-Out check
        if (!empty($record['opted_out'])) {
            return [false, 'EMAIL_OPT_OUT', $record];
        }

        // 3. Application-level global suppression check (Normalized & Case-Insensitive)
        if ($email && GlobalSuppression::isSuppressed($email)) {
            return [false, 'GLOBAL_SUPPRESSION', $record];
        }

        // 4. Status Check (Inactive/Disqualified leads or contacts)
        if (in_array($record['status'], ['Inactive', 'Disqualified', 'Unqualified', 'Lost'])) {
            return [false, 'INACTIVE_RECORD', $record];
        }

        // 5. Consent and GDPR Compliance Validation
        if (($record['consent_status'] ?? 'valid') !== 'valid') {
            return [false, 'MISSING_CONSENT', $record];
        }

        // All checks passed
        return [true, null, $record];
    }

    /**
     * Updates a local Salesforce record (Lead, Contact, Account, or Mock record).
     */
    public function updateSalesforceRecord(string $recordId, array $updates): ?array
    {
        // 1. Mock record
        $mock = SalesforceMockRecord::find($recordId);
        if ($mock) {
            $mock->update($updates);
        }

        // 2. Lead
        $lead = SalesforceLead::where('salesforce_id', $recordId)
            ->orWhere('id', is_numeric($recordId) ? (int)$recordId : -1)
            ->first();

        if ($lead) {
            if (isset($updates['owner_id']) && $lead->owner_id !== $updates['owner_id']) {
                $updates['previous_owner_id'] = $lead->owner_id;
                $updates['owner_verification_status'] = 'changed';
                $updates['last_owner_verified_at'] = Carbon::now();

                // Resolve owner info if possible
                $sfUser = SalesforceUser::where('salesforce_id', $updates['owner_id'])
                    ->orWhere('username', $updates['owner_id'])
                    ->first();
                if ($sfUser) {
                    $updates['owner_name'] = $sfUser->name;
                    $updates['owner_email'] = $sfUser->email;
                }
            }
            $lead->update($updates);
        }

        // 3. Contact
        $contact = SalesforceContact::where('salesforce_id', $recordId)
            ->orWhere('id', is_numeric($recordId) ? (int)$recordId : -1)
            ->first();

        if ($contact) {
            if (isset($updates['owner_id']) && $contact->owner_id !== $updates['owner_id']) {
                $updates['previous_owner_id'] = $contact->owner_id;
                $updates['owner_verification_status'] = 'changed';
                $updates['last_owner_verified_at'] = Carbon::now();

                $sfUser = SalesforceUser::where('salesforce_id', $updates['owner_id'])
                    ->orWhere('username', $updates['owner_id'])
                    ->first();
                if ($sfUser) {
                    $updates['owner_name'] = $sfUser->name;
                    $updates['owner_email'] = $sfUser->email;
                }
            }
            $contact->update($updates);
        }

        // 4. Account
        $account = SalesforceAccount::where('salesforce_id', $recordId)
            ->orWhere('id', is_numeric($recordId) ? (int)$recordId : -1)
            ->first();

        if ($account) {
            if (isset($updates['owner_id']) && $account->owner_id !== $updates['owner_id']) {
                $updates['previous_owner_id'] = $account->owner_id;
                $updates['owner_verification_status'] = 'changed';
                $updates['last_owner_verified_at'] = Carbon::now();
            }
            $account->update($updates);
        }

        return $this->getSalesforceRecord($recordId);
    }

    /**
     * Invalidate cache if required.
     */
    public function invalidateCache(string $recordId): void
    {
        SalesforceCache::where('record_id', $recordId)->delete();
    }

    /**
     * Clear all cache.
     */
    public function clearAllCache(): void
    {
        SalesforceCache::truncate();
    }
}
