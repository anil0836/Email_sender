<?php

namespace App\Services;

use App\Models\GlobalSuppression;
use App\Models\SalesforceCache;
use App\Models\SalesforceMockRecord;
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
     * Retrieves a Salesforce record checking the local cache first.
     * If cache is missing/stale, queries the mock Salesforce records table.
     */
    public function getSalesforceRecord(string $recordId): ?array
    {
        // 1. Check Cache
        $cached = SalesforceCache::find($recordId);
        if ($cached) {
            $data = $cached->toArray();
            $data['id'] = $cached->record_id;
            return $data;
        }

        // 2. Cache miss - query mock Salesforce Database
        $record = SalesforceMockRecord::find($recordId);
        if (!$record) {
            return null;
        }

        $data = $record->toArray();

        // 3. Write to Cache
        SalesforceCache::updateOrCreate(
            ['record_id' => $record->id],
            [
                'object_type' => $record->object_type,
                'first_name' => $record->first_name,
                'last_name' => $record->last_name,
                'email' => $record->email,
                'owner_id' => $record->owner_id,
                'opted_out' => (bool) $record->opted_out,
                'status' => $record->status,
                'consent_status' => $record->consent_status,
                'lawful_basis' => $record->lawful_basis,
                'cached_at' => Carbon::now(),
            ]
        );

        return $data;
    }

    /**
     * Retrieves a Salesforce record by email checking the local cache first.
     */
    public function getSalesforceRecordByEmail(string $email): ?array
    {
        // 1. Check Cache
        $cached = SalesforceCache::where('email', $email)->first();
        if ($cached) {
            $data = $cached->toArray();
            $data['id'] = $cached->record_id;
            return $data;
        }

        // 2. Cache miss - query mock Salesforce Database
        $record = SalesforceMockRecord::where('email', $email)->first();
        if (!$record) {
            return null;
        }

        $data = $record->toArray();

        // 3. Write to Cache
        SalesforceCache::updateOrCreate(
            ['record_id' => $record->id],
            [
                'object_type' => $record->object_type,
                'first_name' => $record->first_name,
                'last_name' => $record->last_name,
                'email' => $record->email,
                'owner_id' => $record->owner_id,
                'opted_out' => (bool) $record->opted_out,
                'status' => $record->status,
                'consent_status' => $record->consent_status,
                'lawful_basis' => $record->lawful_basis,
                'cached_at' => Carbon::now(),
            ]
        );

        return $data;
    }

    /**
     * Invalidates cache for a specific Salesforce record ID.
     */
    public function invalidateCache(string $recordId): void
    {
        SalesforceCache::where('record_id', $recordId)->delete();
    }

    /**
     * Clears all cached Salesforce records.
     */
    public function clearAllCache(): void
    {
        SalesforceCache::truncate();
    }

    /**
     * Updates a mock Salesforce record and invalidates its cache.
     * Simulates Salesforce Change Data Capture (CDC) events.
     */
    public function updateSalesforceRecord(string $recordId, array $updates): void
    {
        $record = SalesforceMockRecord::find($recordId);
        if ($record) {
            $record->update($updates);
        }

        // CDC cache invalidation
        $this->invalidateCache($recordId);
    }

    /**
     * Validates the recipient eligibility at recipient ID level.
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
     * Validates the recipient eligibility at email address level.
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
     * Evaluates compliance and ownership rules against a fetched record.
     */
    protected function evaluateRecordRules(array $record, string $appUsername): array
    {
        $email = $record['email'];

        // 1. Ownership validation
        if ($record['owner_id'] !== $appUsername) {
            return [false, 'DIFFERENT_OWNER', $record];
        }

        // 2. Salesforce Email Opt-Out check (HasOptedOutOfEmail)
        if (!empty($record['opted_out'])) {
            return [false, 'EMAIL_OPT_OUT', $record];
        }

        // 3. Application-level global suppression check
        $isSuppressed = GlobalSuppression::where('email', $email)->exists();
        if ($isSuppressed) {
            return [false, 'GLOBAL_SUPPRESSION', $record];
        }

        // 4. Status Check (Inactive leads or contacts)
        if (in_array($record['status'], ['Inactive', 'Disqualified'])) {
            return [false, 'INACTIVE_RECORD', $record];
        }

        // 5. Consent and GDPR Compliance Validation
        if (($record['consent_status'] ?? '') !== 'valid') {
            return [false, 'MISSING_CONSENT', $record];
        }

        // All checks passed
        return [true, null, $record];
    }
}
