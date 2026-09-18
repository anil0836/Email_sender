<?php

namespace App\Services;

use App\Models\SalesforceSfUser;
use App\Models\SalesforceSyncLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SalesforceSfUserSyncService
{
    /**
     * Synchronize Salesforce Custom Users (SF_User__c) into local database.
     *
     * @param bool $forceFullSync If true, ignores last sync timestamp and performs full sync
     * @param string $syncType cron, manual, artisan
     * @param int|null $limit Maximum records to fetch (optional)
     * @return array
     */
    public function syncSfUsers(bool $forceFullSync = false, string $syncType = 'cron', ?int $limit = null): array
    {
        $startTime = Carbon::now();

        // 1. Create Sync Log Entry (Running state)
        $syncLog = SalesforceSyncLog::create([
            'object_type' => 'SF_User__c',
            'sync_type' => $forceFullSync ? 'full' : $syncType,
            'status' => 'running',
            'started_at' => $startTime,
            'records_fetched' => 0,
            'records_created' => 0,
            'records_updated' => 0,
            'records_skipped' => 0,
            'records_failed' => 0,
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
            Log::error('Salesforce SF_User__c Sync Failed: ' . $errorMsg);

            return [
                'success' => false,
                'error' => $errorMsg,
                'total_fetched' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
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
            Log::error('Salesforce SF_User__c Sync Failed: ' . $errorMsg);

            return [
                'success' => false,
                'error' => $errorMsg,
                'total_fetched' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
        }

        // 2. Determine Incremental Checkpoint
        $since = null;
        if (!$forceFullSync) {
            $latestSuccessful = SalesforceSyncLog::getLatestSuccessfulSync('SF_User__c');
            if ($latestSuccessful && $latestSuccessful->last_modified_checkpoint) {
                $checkpointCarbon = Carbon::parse($latestSuccessful->last_modified_checkpoint)->subMinutes(5);
                $since = $checkpointCarbon->format('Y-m-d\TH:i:s\Z');
            }
        }

        // 3. Build Command Arguments
        $command = ['node', $scriptPath, '--object', 'SF_User__c'];
        if ($since) {
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

        $env = [
            'SF_LOGIN_URL' => $loginUrl,
            'SF_USERNAME' => $username,
            'SF_PASSWORD' => $password,
            'PATH' => getenv('PATH'),
            'NODE_PATH' => base_path('node_modules'),
        ];

        Log::info('Starting Salesforce SF_User__c Sync', [
            'type' => $forceFullSync ? 'full' : $syncType,
            'since' => $since,
            'limit' => $limit,
        ]);

        $process = new Process($command, base_path(), $env, null, 600.0);

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
                ];
            }

            if (!empty($result['error']) || ($result['success'] ?? false) === false) {
                $errorMsg = $result['error'] ?? 'Salesforce synchronization error.';
                $syncLog->update([
                    'status' => 'failed',
                    'completed_at' => Carbon::now(),
                    'error_message' => $errorMsg,
                ]);
                Log::error('Salesforce query error during SF_User__c sync: ' . $errorMsg);

                return [
                    'success' => false,
                    'error' => $errorMsg,
                    'total_fetched' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                ];
            }

            $rawRecords = $result['records'] ?? [];
            $totalFetched = count($rawRecords);
            $newCheckpoint = $result['lastModifiedCheckpoint'] ?? null;

            $createdCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $failedCount = 0;

            $now = Carbon::now();

            // 4. Batch Process and Upsert Records
            $chunks = array_chunk($rawRecords, 200);

            foreach ($chunks as $chunk) {
                DB::beginTransaction();
                try {
                    $salesforceIds = array_filter(array_column($chunk, 'salesforce_id'));
                    $existingSfUsers = SalesforceSfUser::whereIn('salesforce_id', $salesforceIds)
                        ->get()
                        ->keyBy('salesforce_id');

                    foreach ($chunk as $rec) {
                        $sfId = $rec['salesforce_id'] ?? null;
                        if (empty($sfId)) {
                            $skippedCount++;
                            continue;
                        }

                        $sfUserData = [
                            'salesforce_id' => $sfId,
                            'name' => $rec['name'] ?? null,
                            'emp_name' => $rec['emp_name'] ?? null,
                            'full_name_in' => $rec['full_name_in'] ?? null,
                            'emp_email' => $rec['emp_email'] ?? null,
                            'emp_code' => $rec['emp_code'] ?? null,
                            'process' => $rec['process'] ?? null,
                            'team' => $rec['team'] ?? null,
                            'location' => $rec['location'] ?? null,
                            'is_active' => $rec['is_active'] ?? true,
                            'doj' => !empty($rec['doj']) ? Carbon::parse($rec['doj'])->toDateString() : null,
                            'dol' => !empty($rec['dol']) ? Carbon::parse($rec['dol'])->toDateString() : null,
                            'dob' => !empty($rec['dob']) ? Carbon::parse($rec['dob'])->toDateString() : null,
                            'phone' => $rec['phone'] ?? null,
                            'mobile' => $rec['mobile'] ?? null,
                            'linkedin' => $rec['linkedin'] ?? null,
                            'owner_id' => $rec['owner_id'] ?? null,
                            'salesforce_created_at' => !empty($rec['salesforce_created_at']) ? Carbon::parse($rec['salesforce_created_at']) : null,
                            'salesforce_updated_at' => !empty($rec['salesforce_updated_at']) ? Carbon::parse($rec['salesforce_updated_at']) : null,
                            'synced_at' => $now,
                        ];

                        if (isset($existingSfUsers[$sfId])) {
                            $existingSfUsers[$sfId]->update($sfUserData);
                            $updatedCount++;
                        } else {
                            SalesforceSfUser::create($sfUserData);
                            $createdCount++;
                        }
                    }
                    DB::commit();
                } catch (\Throwable $chunkEx) {
                    DB::rollBack();
                    Log::warning('Error during Salesforce SF_User__c chunk upsert: ' . $chunkEx->getMessage());
                    $failedCount += count($chunk);
                }
            }

            // 5. Update Sync Log (Success state)
            $completedAt = Carbon::now();
            $durationSeconds = $startTime->diffInSeconds($completedAt);

            $syncLog->update([
                'status' => 'success',
                'completed_at' => $completedAt,
                'records_fetched' => $totalFetched,
                'records_created' => $createdCount,
                'records_updated' => $updatedCount,
                'records_skipped' => $skippedCount,
                'records_failed' => $failedCount,
                'last_modified_checkpoint' => $newCheckpoint ? Carbon::parse($newCheckpoint) : ($syncLog->last_modified_checkpoint ?? $completedAt),
            ]);

            Log::info("Salesforce SF_User__c Sync Completed successfully in {$durationSeconds}s", [
                'fetched' => $totalFetched,
                'created' => $createdCount,
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
            ]);

            return [
                'success' => true,
                'object' => 'SF_User__c',
                'sync_type' => $syncLog->sync_type,
                'total_fetched' => $totalFetched,
                'created' => $createdCount,
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
                'duration_seconds' => $durationSeconds,
                'last_checkpoint' => $newCheckpoint,
                'message' => "Successfully imported {$createdCount} new SF Users (SF_User__c) and updated {$updatedCount} from Salesforce ({$totalFetched} records fetched).",
            ];

        } catch (\Throwable $e) {
            $errorMsg = 'Exception during SF_User__c sync: ' . $this->maskSecrets($e->getMessage(), $username, $password);
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
            ];
        }
    }

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
