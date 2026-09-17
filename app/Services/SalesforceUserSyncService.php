<?php

namespace App\Services;

use App\Models\SalesforceSyncLog;
use App\Models\SalesforceUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SalesforceUserSyncService
{
    /**
     * Synchronize Salesforce Users into local database.
     *
     * @param bool $forceFullSync If true, ignores last sync timestamp and performs full sync
     * @param string $syncType cron, manual, artisan
     * @param int|null $limit Maximum records to fetch (optional)
     * @return array
     */
    public function syncUsers(bool $forceFullSync = false, string $syncType = 'cron', ?int $limit = null): array
    {
        $startTime = Carbon::now();

        // 1. Create Sync Log Entry (Running state)
        $syncLog = SalesforceSyncLog::create([
            'object_type' => 'User',
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
            Log::error('Salesforce User Sync Failed: ' . $errorMsg);

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
            Log::error('Salesforce User Sync Failed: ' . $errorMsg);

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
            $latestSuccessful = SalesforceSyncLog::getLatestSuccessfulSync('User');
            if ($latestSuccessful && $latestSuccessful->last_modified_checkpoint) {
                $checkpointCarbon = Carbon::parse($latestSuccessful->last_modified_checkpoint)->subMinutes(5);
                $since = $checkpointCarbon->format('Y-m-d\TH:i:s\Z');
            }
        }

        // 3. Build Command Arguments
        $command = ['node', $scriptPath, '--object', 'User'];
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

        Log::info('Starting Salesforce User Sync', [
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
                Log::error('Salesforce query error during User sync: ' . $errorMsg);

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
                    $existingUsers = SalesforceUser::whereIn('salesforce_id', $salesforceIds)
                        ->get()
                        ->keyBy('salesforce_id');

                    foreach ($chunk as $rec) {
                        $sfId = $rec['salesforce_id'] ?? null;
                        if (empty($sfId)) {
                            $skippedCount++;
                            continue;
                        }

                        $userData = [
                            'salesforce_id' => $sfId,
                            'username' => $rec['username'] ?? null,
                            'first_name' => $rec['first_name'] ?? null,
                            'last_name' => $rec['last_name'] ?? null,
                            'name' => $rec['name'] ?? null,
                            'email' => $rec['email'] ?? null,
                            'title' => $rec['title'] ?? null,
                            'department' => $rec['department'] ?? null,
                            'company_name' => $rec['company_name'] ?? null,
                            'division' => $rec['division'] ?? null,
                            'phone' => $rec['phone'] ?? null,
                            'mobile_phone' => $rec['mobile_phone'] ?? null,
                            'city' => $rec['city'] ?? null,
                            'state' => $rec['state'] ?? null,
                            'country' => $rec['country'] ?? null,
                            'is_active' => $rec['is_active'] ?? true,
                            'user_type' => $rec['user_type'] ?? null,
                            'profile_id' => $rec['profile_id'] ?? null,
                            'user_role_id' => $rec['user_role_id'] ?? null,
                            'manager_id' => $rec['manager_id'] ?? null,
                            'salesforce_created_at' => !empty($rec['salesforce_created_at']) ? Carbon::parse($rec['salesforce_created_at']) : null,
                            'salesforce_updated_at' => !empty($rec['salesforce_updated_at']) ? Carbon::parse($rec['salesforce_updated_at']) : null,
                            'synced_at' => $now,
                        ];

                        if (isset($existingUsers[$sfId])) {
                            $existingUsers[$sfId]->update($userData);
                            $updatedCount++;
                        } else {
                            SalesforceUser::create($userData);
                            $createdCount++;
                        }
                    }
                    DB::commit();
                } catch (\Throwable $chunkEx) {
                    DB::rollBack();
                    Log::warning('Error during Salesforce user chunk upsert: ' . $chunkEx->getMessage());
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

            Log::info("Salesforce User Sync Completed successfully in {$durationSeconds}s", [
                'fetched' => $totalFetched,
                'created' => $createdCount,
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
            ]);

            return [
                'success' => true,
                'object' => 'User',
                'sync_type' => $syncLog->sync_type,
                'total_fetched' => $totalFetched,
                'created' => $createdCount,
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
                'duration_seconds' => $durationSeconds,
                'last_checkpoint' => $newCheckpoint,
                'message' => "Successfully imported {$createdCount} new users and updated {$updatedCount} users from Salesforce ({$totalFetched} records fetched).",
            ];

        } catch (\Throwable $e) {
            $errorMsg = 'Exception during User sync: ' . $this->maskSecrets($e->getMessage(), $username, $password);
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
