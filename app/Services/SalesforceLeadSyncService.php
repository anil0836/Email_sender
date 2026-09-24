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

        $tempDir = storage_path('app/salesforce');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }
        $tempFilePath = $tempDir . '/leads_' . time() . '_' . uniqid() . '.jsonl';

        $command[] = '--output-file';
        $command[] = $tempFilePath;

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
            'temp_file' => $tempFilePath,
        ]);

        $timeout = $forceFullSync ? 3600.0 : 900.0;
        $process = new Process($command, base_path(), $env, null, $timeout);

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

            $outputFile = $result['outputFile'] ?? $tempFilePath;
            $newCheckpoint = $result['lastModifiedCheckpoint'] ?? null;
            $now = Carbon::now();

            // 4. Batch Process and Upsert Records with Owner Mapping (Streamed file or In-Memory array)
            if (file_exists($outputFile) && filesize($outputFile) > 0) {
                $processStats = $this->processLeadRecords($outputFile, $now);
                $totalFetched = (int) ($result['totalFetched'] ?? ($processStats['created'] + $processStats['updated'] + $processStats['skipped'] + $processStats['failed']));
                @unlink($outputFile);
            } else {
                $rawRecords = $result['records'] ?? [];
                $totalFetched = count($rawRecords);
                $processStats = $this->processLeadRecords($rawRecords, $now);
            }

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
            if (isset($tempFilePath) && file_exists($tempFilePath)) {
                @unlink($tempFilePath);
            }
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
     * Supports both in-memory array of records and JSON Lines streamed file path.
     * Maps Custom_Owner__c to local salesforce_sf_users, resolves ownership, and stores Deal_Category__c.
     *
     * @param array|string $recordsOrFilePath Array of records or file path to JSONL file
     * @param Carbon|null $now
     * @return array
     */
    public function processLeadRecords(array|string $recordsOrFilePath, ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now();
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'owners_mapped' => 0,
            'owners_not_mapped' => 0,
        ];

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

        $chunkSize = 500;

        if (is_string($recordsOrFilePath) && file_exists($recordsOrFilePath)) {
            $handle = @fopen($recordsOrFilePath, 'r');
            if ($handle) {
                $buffer = [];
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $rec = json_decode($line, true);
                    if (!is_array($rec)) continue;

                    $buffer[] = $rec;
                    if (count($buffer) >= $chunkSize) {
                        $this->processLeadChunk(
                            $buffer, $now, $stats, $standardUsersMap,
                            $standardUsersNames, $sfUsersBySfId, $sfUsersByName, $sfUsersByEmpName
                        );
                        $buffer = [];
                    }
                }
                if (!empty($buffer)) {
                    $this->processLeadChunk(
                        $buffer, $now, $stats, $standardUsersMap,
                        $standardUsersNames, $sfUsersBySfId, $sfUsersByName, $sfUsersByEmpName
                    );
                    $buffer = [];
                }
                fclose($handle);
            }
        } elseif (is_array($recordsOrFilePath)) {
            $chunks = array_chunk($recordsOrFilePath, $chunkSize);
            foreach ($chunks as $chunk) {
                $this->processLeadChunk(
                    $chunk, $now, $stats, $standardUsersMap,
                    $standardUsersNames, $sfUsersBySfId, $sfUsersByName, $sfUsersByEmpName
                );
            }
        }

        return $stats;
    }

    /**
     * Process a single chunk of lead records with bulk upsert and owner resolution.
     */
    protected function processLeadChunk(
        array $chunk,
        Carbon $now,
        array &$stats,
        array $standardUsersMap,
        array $standardUsersNames,
        $sfUsersBySfId,
        array $sfUsersByName,
        array $sfUsersByEmpName
    ): void {
        if (empty($chunk)) {
            return;
        }

        DB::beginTransaction();
        try {
            $salesforceIds = [];
            foreach ($chunk as $r) {
                $id = trim((string)($r['salesforce_id'] ?? ''));
                if (!empty($id)) {
                    $salesforceIds[] = $id;
                }
            }

            $existingLeads = !empty($salesforceIds)
                ? DB::table('salesforce_leads')
                    ->whereIn('salesforce_id', $salesforceIds)
                    ->get([
                        'id', 'salesforce_id', 'owner_id', 'prime_owner_id',
                        'salesforce_sf_user_id', 'owner_name', 'owner_email',
                        'owner_verification_status', 'previous_owner_id',
                        'custom_owner', 'Custom_Owner__c', 'Deal_Category__c',
                        'created_at', 'updated_at'
                    ])
                    ->keyBy('salesforce_id')
                : collect();

            $convertedIdsToDelete = [];
            $recordsToUpsert = [];

            $truncate = static function (?string $value, int $maxLength): ?string {
                if ($value === null) {
                    return null;
                }
                $value = trim($value);
                if ($value === '') {
                    return null;
                }
                return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength) : $value;
            };

            $parseDate = static function ($val): ?string {
                if (empty($val)) {
                    return null;
                }
                try {
                    return Carbon::parse($val)->toDateTimeString();
                } catch (\Throwable $e) {
                    return null;
                }
            };

            foreach ($chunk as $rec) {
                $sfId = $truncate((string)($rec['salesforce_id'] ?? ''), 50);
                if (empty($sfId)) {
                    $stats['skipped']++;
                    continue;
                }

                // Enforce IsConverted = false: if IsConverted is true, do not store it as a lead
                $isConverted = $rec['is_converted'] ?? $rec['IsConverted'] ?? false;
                if ($isConverted === true || $isConverted === 'true' || $isConverted === 1 || $isConverted === '1') {
                    $stats['skipped']++;
                    if (isset($existingLeads[$sfId])) {
                        $convertedIdsToDelete[] = $sfId;
                    }
                    continue;
                }

                $rawCustomOwner = $rec['custom_owner'] ?? $rec['Custom_Owner__c'] ?? $rec['CustomOwner'] ?? null;
                $rawCustomOwner = ($rawCustomOwner !== null && trim((string)$rawCustomOwner) !== '')
                    ? (string)$rawCustomOwner
                    : null;
                $incomingOwnerId = $truncate($rec['owner_id'] ?? $rec['OwnerId'] ?? null, 50);
                $incomingPrimeOwnerId = $truncate($rec['prime_owner_id'] ?? $rec['Prime_Owner__c'] ?? null, 50);

                $matchedSfUser = null;
                $salesforceSfUserId = null;
                $ownerName = null;
                $ownerEmail = null;

                // Priority 1: Custom_Owner__c matched against salesforce_sf_users.name or emp_name (case-insensitive, trimmed)
                if ($rawCustomOwner !== null) {
                    $normCustom = strtolower(trim($rawCustomOwner));
                    if (isset($sfUsersByName[$normCustom])) {
                        $matchedSfUser = $sfUsersByName[$normCustom];
                    } elseif (isset($sfUsersByEmpName[$normCustom])) {
                        $matchedSfUser = $sfUsersByEmpName[$normCustom];
                    }

                    if ($matchedSfUser) {
                        $salesforceSfUserId = $matchedSfUser->id;
                        $incomingPrimeOwnerId = $truncate($matchedSfUser->salesforce_id, 50);
                        $ownerName = $matchedSfUser->name ?: $matchedSfUser->emp_name;
                        $ownerEmail = $matchedSfUser->emp_email;
                        $stats['owners_mapped']++;
                    } else {
                        $stats['owners_not_mapped']++;
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

                $isExisting = isset($existingLeads[$sfId]);
                if ($isExisting) {
                    $existing = $existingLeads[$sfId];
                    if ($existing->owner_id && $incomingOwnerId && $existing->owner_id !== $incomingOwnerId) {
                        $verificationStatus = 'changed';
                        $previousOwnerId = $truncate($existing->owner_id, 50);
                    } else {
                        $verificationStatus = $existing->owner_verification_status ?: 'verified';
                        $previousOwnerId = $truncate($existing->previous_owner_id, 50);
                    }

                    // Priority 3 preservation: If incoming Custom_Owner__c is null, preserve existing owner mapping/fallback
                    if ($rawCustomOwner === null) {
                        $rawCustomOwner = $existing->Custom_Owner__c ?: $existing->custom_owner;
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
                            $incomingPrimeOwnerId = $truncate($existing->prime_owner_id, 50);
                        }
                        if (empty($incomingOwnerId) && $existing->owner_id) {
                            $incomingOwnerId = $truncate($existing->owner_id, 50);
                        }
                    }
                    $stats['updated']++;
                } else {
                    $stats['created']++;
                }

                // Deal_Category__c resolution: retrieve from Deal_Category__c or deal_category
                $dealCategory = $rec['Deal_Category__c'] ?? $rec['deal_category'] ?? null;
                if (($dealCategory === null || $dealCategory === '') && $isExisting && !empty($existingLeads[$sfId]->Deal_Category__c)) {
                    $dealCategory = $existingLeads[$sfId]->Deal_Category__c;
                }

                $leadData = [
                    'salesforce_id' => $sfId,
                    'first_name' => $truncate($rec['first_name'] ?? null, 150),
                    'last_name' => $truncate($rec['last_name'] ?? null, 150),
                    'name' => $truncate($rec['name'] ?? null, 255),
                    'company' => $truncate($rec['company'] ?? null, 255),
                    'title' => $truncate($rec['title'] ?? null, 255),
                    'email' => $truncate($rec['email'] ?? null, 255),
                    'phone' => $truncate($rec['phone'] ?? null, 100),
                    'mobile_phone' => $truncate($rec['mobile_phone'] ?? null, 100),
                    'website' => $truncate($rec['website'] ?? null, 255),
                    'lead_source' => $truncate($rec['lead_source'] ?? null, 150),
                    'industry' => $truncate($rec['industry'] ?? null, 150),
                    'Deal_Category__c' => $truncate($dealCategory, 255),
                    'status' => $truncate($rec['status'] ?? 'New', 100),
                    'is_converted' => 0,
                    'street' => $truncate($rec['street'] ?? null, 65000),
                    'city' => $truncate($rec['city'] ?? null, 150),
                    'state' => $truncate($rec['state'] ?? null, 150),
                    'postal_code' => $truncate($rec['postal_code'] ?? null, 50),
                    'country' => $truncate($rec['country'] ?? null, 150),
                    'owner_id' => $incomingOwnerId,
                    'prime_owner_id' => $incomingPrimeOwnerId,
                    'salesforce_sf_user_id' => $salesforceSfUserId,
                    'secondary_owner' => $truncate($rec['secondary_owner'] ?? null, 150),
                    'custom_owner' => $truncate($rawCustomOwner, 150),
                    'Custom_Owner__c' => $truncate($rawCustomOwner, 255),
                    'owner_name' => $truncate($ownerName, 255),
                    'owner_email' => $truncate($ownerEmail, 255),
                    'owner_verification_status' => $truncate($verificationStatus, 50),
                    'last_owner_verified_at' => $now->toDateTimeString(),
                    'previous_owner_id' => $previousOwnerId,
                    'salesforce_created_at' => $parseDate($rec['salesforce_created_at'] ?? null),
                    'salesforce_updated_at' => $parseDate($rec['salesforce_updated_at'] ?? null),
                    'synced_at' => $now->toDateTimeString(),
                    'created_at' => $isExisting ? ($existingLeads[$sfId]->created_at ?? $now->toDateTimeString()) : $now->toDateTimeString(),
                    'updated_at' => $now->toDateTimeString(),
                ];

                $recordsToUpsert[$sfId] = $leadData;
            }

            $upsertColumns = [
                'first_name', 'last_name', 'name', 'company', 'title', 'email',
                'phone', 'mobile_phone', 'website', 'lead_source', 'industry',
                'Deal_Category__c', 'status', 'is_converted', 'street', 'city',
                'state', 'postal_code', 'country', 'owner_id', 'prime_owner_id',
                'salesforce_sf_user_id', 'secondary_owner', 'custom_owner',
                'Custom_Owner__c', 'owner_name', 'owner_email',
                'owner_verification_status', 'last_owner_verified_at',
                'previous_owner_id', 'salesforce_created_at',
                'salesforce_updated_at', 'synced_at', 'updated_at'
            ];

            if (!empty($convertedIdsToDelete)) {
                DB::table('salesforce_leads')->whereIn('salesforce_id', $convertedIdsToDelete)->delete();
            }

            if (!empty($recordsToUpsert)) {
                DB::table('salesforce_leads')->upsert(
                    array_values($recordsToUpsert),
                    ['salesforce_id'],
                    $upsertColumns
                );
            }

            DB::commit();
        } catch (\Throwable $chunkEx) {
            DB::rollBack();
            Log::warning('Error during Salesforce lead chunk upsert, falling back to individual record upserts: ' . $chunkEx->getMessage());

            if (!empty($convertedIdsToDelete)) {
                try {
                    DB::table('salesforce_leads')->whereIn('salesforce_id', $convertedIdsToDelete)->delete();
                } catch (\Throwable $delEx) {
                    Log::warning('Error deleting converted leads during fallback: ' . $delEx->getMessage());
                }
            }

            foreach ($recordsToUpsert as $sfId => $leadData) {
                try {
                    DB::table('salesforce_leads')->upsert(
                        [$leadData],
                        ['salesforce_id'],
                        $upsertColumns
                    );
                } catch (\Throwable $singleEx) {
                    $stats['failed']++;
                    if (isset($existingLeads[$sfId])) {
                        $stats['updated'] = max(0, $stats['updated'] - 1);
                    } else {
                        $stats['created'] = max(0, $stats['created'] - 1);
                    }
                    Log::error("Failed to upsert single lead record [{$sfId}]: " . $singleEx->getMessage());
                }
            }
        }
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
