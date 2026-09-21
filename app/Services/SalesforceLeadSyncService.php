<?php

namespace App\Services;

use App\Models\SalesforceLead;
use App\Models\SalesforceSyncLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SalesforceLeadSyncService
{
    /**
     * Synchronize Salesforce Leads into local database.
     *
     * @param bool $forceFullSync If true, ignores last sync timestamp and performs full sync
     * @param string $syncType cron, manual, artisan
     * @param int|null $limit Maximum records to fetch (optional)
     * @param bool $todayOnly If true, only sync records starting from today
     * @param string $order Sorting direction (DESC by default to fetch latest records first)
     * @return array
     */
    public function syncLeads(
        bool $forceFullSync = false, 
        string $syncType = 'cron', 
        ?int $limit = null, 
        bool $todayOnly = false,
        string $order = 'DESC'
    ): array {
        $startTime = Carbon::now();

        $effectiveSyncType = $todayOnly ? 'today' : ($forceFullSync ? 'full' : $syncType);

        // 1. Create Sync Log Entry (Running state)
        $syncLog = SalesforceSyncLog::create([
            'object_type' => 'Lead',
            'sync_type' => $effectiveSyncType,
            'status' => 'running',
            'started_at' => $startTime,
            'records_fetched' => 0,
            'records_created' => 0,
            'records_updated' => 0,
            'records_skipped' => 0,
            'records_failed' => 0,
            'owners_mapped' => 0,
            'owners_not_mapped' => 0,
        ]);

        $loginUrl = (string) (config('services.salesforce.login_url') ?: env('SF_LOGIN_URL', env('SALESFORCE_LOGIN_URL', 'https://login.salesforce.com')));
        $username = (string) (config('services.salesforce.username') ?: env('SF_USERNAME', env('SALESFORCE_USERNAME', '')));
        $password = (string) (config('services.salesforce.password') ?: env('SF_PASSWORD', env('SALESFORCE_PASSWORD', '')));

        if (empty(trim($username)) || empty(trim($password))) {
            $errorMsg = 'Salesforce credentials missing in configuration (.env).';
            $syncLog->update([
                'status' => 'failed',
                'completed_at' => Carbon::now(),
                'error_message' => $errorMsg,
            ]);
            Log::error('Salesforce Lead Sync Failed: ' . $errorMsg);

            return [
                'success' => false,
                'error' => $errorMsg,
                'total_fetched' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'owners_mapped' => 0,
                'owners_not_mapped' => 0,
            ];
        }

        $scriptPath = base_path('scripts/salesforce_sync_runner.js');
        if (!file_exists($scriptPath)) {
            $errorMsg = 'Salesforce sync runner script is missing at: ' . $scriptPath;
            $syncLog->update([
                'status' => 'failed',
                'completed_at' => Carbon::now(),
                'error_message' => $errorMsg,
            ]);
            Log::error('Salesforce Lead Sync Failed: ' . $errorMsg);

            return [
                'success' => false,
                'error' => $errorMsg,
                'total_fetched' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'owners_mapped' => 0,
                'owners_not_mapped' => 0,
            ];
        }

        // 2. Determine Incremental Checkpoint
        $since = null;
        if (!$forceFullSync && !$todayOnly) {
            $latestSuccessful = SalesforceSyncLog::getLatestSuccessfulSync('Lead');
            if ($latestSuccessful && $latestSuccessful->last_modified_checkpoint) {
                // Buffer by 5 minutes to avoid missing records near boundary
                $checkpointCarbon = Carbon::parse($latestSuccessful->last_modified_checkpoint)->subMinutes(5);
                $since = $checkpointCarbon->format('Y-m-d\TH:i:s\Z');
            }
        }

        // 3. Build Command Arguments
        $command = ['node', $scriptPath, '--object', 'Lead'];
        if ($todayOnly) {
            $command[] = '--today';
        } elseif ($since) {
            $command[] = '--since';
            $command[] = $since;
        }
        if ($forceFullSync) {
            $command[] = '--full';
        }
        if ($limit && $limit > 0) {
            $command[] = '--limit';
            $command[] = (string) $limit;
        }
        $command[] = '--order';
        $command[] = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $env = [
            'SF_LOGIN_URL' => $loginUrl,
            'SF_USERNAME' => $username,
            'SF_PASSWORD' => $password,
            'PATH' => getenv('PATH'),
            'NODE_PATH' => base_path('node_modules'),
        ];

        Log::info('Starting Salesforce Lead Sync', [
            'type' => $forceFullSync ? 'full' : $syncType,
            'since' => $since,
            'limit' => $limit,
        ]);

        $process = new Process($command, base_path(), $env, null, 600.0); // 10 minute timeout

        try {
            $process->run();

            $output = trim($process->getOutput());
            $errorOutput = trim($process->getErrorOutput());

            if (!$process->isSuccessful() && empty($output)) {
                $errorMsg = 'Salesforce JSforce process failed: ' . $this->maskSecrets($errorOutput, $username, $password);
                $syncLog->update([
                    'status' => 'failed',
                    'completed_at' => Carbon::now(),
                    'error_message' => $errorMsg,
                ]);
                Log::error($errorMsg);

                return [
                    'success' => false,
                    'error' => $errorMsg,
                    'total_fetched' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                    'owners_mapped' => 0,
                    'owners_not_mapped' => 0,
                ];
            }

            $result = json_decode($output, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
                $errorMsg = 'Invalid JSON response from sync runner: ' . json_last_error_msg();
                $syncLog->update([
                    'status' => 'failed',
                    'completed_at' => Carbon::now(),
                    'error_message' => $errorMsg,
                ]);
                Log::error($errorMsg, ['raw_output' => substr($output, 0, 500)]);

                return [
                    'success' => false,
                    'error' => $errorMsg,
                    'total_fetched' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                    'owners_mapped' => 0,
                    'owners_not_mapped' => 0,
                ];
            }

            if (!empty($result['error']) || ($result['success'] ?? false) === false) {
                $errorMsg = $result['error'] ?? 'Salesforce synchronization error.';
                $syncLog->update([
                    'status' => 'failed',
                    'completed_at' => Carbon::now(),
                    'error_message' => $errorMsg,
                ]);
                Log::error('Salesforce query error during sync: ' . $errorMsg);

                return [
                    'success' => false,
                    'error' => $errorMsg,
                    'total_fetched' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                    'owners_mapped' => 0,
                    'owners_not_mapped' => 0,
                ];
            }

            $rawRecords = $result['records'] ?? [];
            $totalFetched = count($rawRecords);
            $newCheckpoint = $result['lastModifiedCheckpoint'] ?? null;
            $now = Carbon::now();

            // 4. Batch Process and Upsert Records with Owner Mapping
            $processStats = $this->processLeadRecords($rawRecords, $now);

            // 5. Update Sync Log (Success state)
            $completedAt = Carbon::now();
            $durationSeconds = $startTime->diffInSeconds($completedAt);

            $syncLog->update([
                'status' => 'success',
                'completed_at' => $completedAt,
                'records_fetched' => $totalFetched,
                'records_created' => $processStats['created'],
                'records_updated' => $processStats['updated'],
                'records_skipped' => $processStats['skipped'],
                'records_failed' => $processStats['failed'],
                'owners_mapped' => $processStats['owners_mapped'],
                'owners_not_mapped' => $processStats['owners_not_mapped'],
                'last_modified_checkpoint' => $newCheckpoint ? Carbon::parse($newCheckpoint) : ($syncLog->last_modified_checkpoint ?? $completedAt),
            ]);

            Log::info("Salesforce Lead Sync Completed successfully in {$durationSeconds}s", [
                'fetched' => $totalFetched,
                'created' => $processStats['created'],
                'updated' => $processStats['updated'],
                'skipped' => $processStats['skipped'],
                'failed' => $processStats['failed'],
                'owners_mapped' => $processStats['owners_mapped'],
                'owners_not_mapped' => $processStats['owners_not_mapped'],
            ]);

            return [
                'success' => true,
                'sync_type' => $syncLog->sync_type,
                'total_fetched' => $totalFetched,
                'created' => $processStats['created'],
                'updated' => $processStats['updated'],
                'skipped' => $processStats['skipped'],
                'failed' => $processStats['failed'],
                'owners_mapped' => $processStats['owners_mapped'],
                'owners_not_mapped' => $processStats['owners_not_mapped'],
                'duration_seconds' => $durationSeconds,
                'last_checkpoint' => $newCheckpoint,
                'message' => "Successfully imported {$processStats['created']} new leads and updated {$processStats['updated']} leads from Salesforce ({$totalFetched} records fetched, {$processStats['owners_mapped']} owners mapped).",
            ];

        } catch (\Throwable $e) {
            $errorMsg = 'Exception during sync: ' . $this->maskSecrets($e->getMessage(), $username, $password);
            $syncLog->update([
                'status' => 'failed',
                'completed_at' => Carbon::now(),
                'error_message' => $errorMsg,
            ]);
            Log::error($errorMsg);

            return [
                'success' => false,
                'error' => $errorMsg,
                'total_fetched' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'owners_mapped' => 0,
                'owners_not_mapped' => 0,
            ];
        }
    }

    /**
     * Batch process and upsert raw Salesforce Lead records into local database.
     * Maps Custom_Owner__c to local salesforce_sf_users and resolves ownership.
     *
     * @param array $rawRecords
     * @param Carbon|null $now
     * @return array
     */
    public function processLeadRecords(array $rawRecords, ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now();
        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $ownersMappedCount = 0;
        $ownersNotMappedCount = 0;

        // Pre-load standard users and SF custom users for fast owner resolution without N+1 queries
        $standardUsers = \App\Models\SalesforceUser::all();
        $standardUsersMap = $standardUsers->pluck('email', 'salesforce_id')->toArray();
        $standardUsersNames = $standardUsers->pluck('name', 'salesforce_id')->toArray();

        $sfUsers = \App\Models\SalesforceSfUser::all();
        $sfUsersBySfId = $sfUsers->keyBy('salesforce_id');

        // Build lookup tables for Custom_Owner__c matching (case-insensitive & trimmed)
        $sfUsersByName = [];
        $sfUsersByEmpName = [];
        foreach ($sfUsers as $u) {
            if (!empty(trim((string)$u->name))) {
                $normalizedName = strtolower(trim((string)$u->name));
                if (!isset($sfUsersByName[$normalizedName])) {
                    $sfUsersByName[$normalizedName] = $u;
                }
            }
            if (!empty(trim((string)$u->emp_name))) {
                $normalizedEmpName = strtolower(trim((string)$u->emp_name));
                if (!isset($sfUsersByEmpName[$normalizedEmpName])) {
                    $sfUsersByEmpName[$normalizedEmpName] = $u;
                }
            }
        }

        // Batch process in chunks of 200
        $chunks = array_chunk($rawRecords, 200);

        foreach ($chunks as $chunk) {
            DB::beginTransaction();
            try {
                $salesforceIds = array_filter(array_column($chunk, 'salesforce_id'));
                $existingLeads = SalesforceLead::whereIn('salesforce_id', $salesforceIds)
                    ->get()
                    ->keyBy('salesforce_id');

                foreach ($chunk as $rec) {
                    $sfId = $rec['salesforce_id'] ?? null;
                    if (empty($sfId)) {
                        $skippedCount++;
                        continue;
                    }

                    // Enforce IsConverted = false: if IsConverted is true, do not store it as a lead
                    $isConverted = $rec['is_converted'] ?? $rec['IsConverted'] ?? false;
                    if ($isConverted === true || $isConverted === 'true' || $isConverted === 1 || $isConverted === '1') {
                        $skippedCount++;
                        // If lead was previously stored and has now been converted in Salesforce, remove it
                        if (isset($existingLeads[$sfId])) {
                            $existingLeads[$sfId]->delete();
                            unset($existingLeads[$sfId]);
                        }
                        continue;
                    }

                    $rawCustomOwner = $rec['custom_owner'] ?? $rec['Custom_Owner__c'] ?? $rec['CustomOwner'] ?? null;
                    $rawCustomOwner = ($rawCustomOwner !== null && trim((string)$rawCustomOwner) !== '')
                        ? (string)$rawCustomOwner
                        : null;
                    $incomingOwnerId = $rec['owner_id'] ?? $rec['OwnerId'] ?? null;
                    $incomingPrimeOwnerId = $rec['prime_owner_id'] ?? $rec['Prime_Owner__c'] ?? null;

                    $matchedSfUser = null;
                    $salesforceSfUserId = null;
                    $ownerName = null;
                    $ownerEmail = null;

                    // Priority 1: Custom_Owner__c matched against salesforce_sf_users.name (case-insensitive, trimmed)
                    if ($rawCustomOwner !== null) {
                        $normCustom = strtolower(trim($rawCustomOwner));
                        if (isset($sfUsersByName[$normCustom])) {
                            $matchedSfUser = $sfUsersByName[$normCustom];
                        } elseif (isset($sfUsersByEmpName[$normCustom])) {
                            $matchedSfUser = $sfUsersByEmpName[$normCustom];
                        }

                        if ($matchedSfUser) {
                            $salesforceSfUserId = $matchedSfUser->id;
                            $incomingPrimeOwnerId = $matchedSfUser->salesforce_id;
                            $ownerName = $matchedSfUser->name ?: $matchedSfUser->emp_name;
                            $ownerEmail = $matchedSfUser->emp_email;
                            $ownersMappedCount++;
                        } else {
                            $ownersNotMappedCount++;
                            Log::warning("Lead Owner Mapping Failed | Salesforce Lead ID: {$sfId} | Custom_Owner__c: {$rawCustomOwner} | Reason: No matching salesforce_sf_users.name found.");
                        }
                    }

                    // Priority 2: Fallback to Prime Owner (Prime_Owner__c) in salesforce_sf_users
                    if (!$matchedSfUser && !empty($incomingPrimeOwnerId) && isset($sfUsersBySfId[$incomingPrimeOwnerId])) {
                        $matchedSfUser = $sfUsersBySfId[$incomingPrimeOwnerId];
                        $salesforceSfUserId = $matchedSfUser->id;
                        $ownerName = $matchedSfUser->name ?: $matchedSfUser->emp_name;
                        $ownerEmail = $matchedSfUser->emp_email;
                    }

                    // Priority 3: Fallback to Standard Salesforce User (owner_id)
                    if (empty($ownerName)) {
                        $ownerName = $standardUsersNames[$incomingOwnerId] ?? null;
                    }
                    if (empty($ownerEmail)) {
                        $ownerEmail = $standardUsersMap[$incomingOwnerId] ?? null;
                    }

                    $verificationStatus = 'verified';
                    $previousOwnerId = null;

                    if (isset($existingLeads[$sfId])) {
                        $existing = $existingLeads[$sfId];
                        if ($existing->owner_id && $incomingOwnerId && $existing->owner_id !== $incomingOwnerId) {
                            $verificationStatus = 'changed';
                            $previousOwnerId = $existing->owner_id;
                        } else {
                            $verificationStatus = $existing->owner_verification_status ?: 'verified';
                            $previousOwnerId = $existing->previous_owner_id;
                        }

                        // Priority 3 preservation: If incoming Custom_Owner__c is null, preserve existing owner mapping/fallback
                        if ($rawCustomOwner === null) {
                            if ($salesforceSfUserId === null && $existing->salesforce_sf_user_id) {
                                $salesforceSfUserId = $existing->salesforce_sf_user_id;
                            }
                            if (empty($ownerName) && $existing->owner_name) {
                                $ownerName = $existing->owner_name;
                            }
                            if (empty($ownerEmail) && $existing->owner_email) {
                                $ownerEmail = $existing->owner_email;
                            }
                            if (empty($incomingPrimeOwnerId) && $existing->prime_owner_id) {
                                $incomingPrimeOwnerId = $existing->prime_owner_id;
                            }
                            if (empty($incomingOwnerId) && $existing->owner_id) {
                                $incomingOwnerId = $existing->owner_id;
                            }
                        }
                    }

                    $leadData = [
                        'salesforce_id' => $sfId,
                        'first_name' => $rec['first_name'] ?? null,
                        'last_name' => $rec['last_name'] ?? null,
                        'name' => $rec['name'] ?? null,
                        'company' => $rec['company'] ?? null,
                        'title' => $rec['title'] ?? null,
                        'email' => $rec['email'] ?? null,
                        'phone' => $rec['phone'] ?? null,
                        'mobile_phone' => $rec['mobile_phone'] ?? null,
                        'website' => $rec['website'] ?? null,
                        'lead_source' => $rec['lead_source'] ?? null,
                        'industry' => $rec['industry'] ?? null,
                        'status' => $rec['status'] ?? 'New',
                        'is_converted' => false,
                        'street' => $rec['street'] ?? null,
                        'city' => $rec['city'] ?? null,
                        'state' => $rec['state'] ?? null,
                        'postal_code' => $rec['postal_code'] ?? null,
                        'country' => $rec['country'] ?? null,
                        'owner_id' => $incomingOwnerId,
                        'prime_owner_id' => $incomingPrimeOwnerId,
                        'salesforce_sf_user_id' => $salesforceSfUserId,
                        'secondary_owner' => $rec['secondary_owner'] ?? null,
                        'custom_owner' => $rawCustomOwner,
                        'Custom_Owner__c' => $rawCustomOwner,
                        'owner_name' => $ownerName,
                        'owner_email' => $ownerEmail,
                        'owner_verification_status' => $verificationStatus,
                        'last_owner_verified_at' => $now,
                        'previous_owner_id' => $previousOwnerId,
                        'salesforce_created_at' => !empty($rec['salesforce_created_at']) ? Carbon::parse($rec['salesforce_created_at']) : null,
                        'salesforce_updated_at' => !empty($rec['salesforce_updated_at']) ? Carbon::parse($rec['salesforce_updated_at']) : null,
                        'synced_at' => $now,
                    ];

                    if (isset($existingLeads[$sfId])) {
                        $existingLeads[$sfId]->update($leadData);
                        $updatedCount++;
                    } else {
                        SalesforceLead::create($leadData);
                        $createdCount++;
                    }
                }
                DB::commit();
            } catch (\Throwable $chunkEx) {
                DB::rollBack();
                Log::warning('Error during Salesforce lead chunk upsert: ' . $chunkEx->getMessage());
                $failedCount += count($chunk);
            }
        }

        return [
            'created' => $createdCount,
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
            'failed' => $failedCount,
            'owners_mapped' => $ownersMappedCount,
            'owners_not_mapped' => $ownersNotMappedCount,
        ];
    }

    /**
     * Mask sensitive credentials from strings before logging
     */
    protected function maskSecrets(string $text, string $username, string $password): string
    {
        if (!empty($username)) {
            $text = str_replace($username, '***', $text);
        }
        if (!empty($password)) {
            $text = str_replace($password, '***', $text);
        }
        return $text;
    }
}
