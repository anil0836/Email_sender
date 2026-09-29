<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add Custom_Owner__c column to salesforce_contacts
        if (Schema::hasTable('salesforce_contacts')) {
            Schema::table('salesforce_contacts', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_contacts', 'Custom_Owner__c')) {
                    $table->string('Custom_Owner__c', 255)->nullable()->after('secondary_owner')->index();
                }
            });

            // 2. Fetch and populate Custom_Owner__c values from Salesforce Contact records
            if (!app()->environment('testing')) {
                $jsonlPath = storage_path('app/salesforce/contact_custom_owners.jsonl');
                $scriptPath = base_path('scripts/fetch_contact_custom_owners.js');

                if (!file_exists($jsonlPath) && file_exists($scriptPath)) {
                    try {
                        $process = new Process(['node', $scriptPath, $jsonlPath], base_path(), null, null, 180.0);
                        $process->run();
                    } catch (\Throwable $e) {
                        Log::warning('Could not execute fetch_contact_custom_owners.js during migration: ' . $e->getMessage());
                    }
                }

                if (file_exists($jsonlPath) && filesize($jsonlPath) > 0) {
                    $handle = @fopen($jsonlPath, 'r');
                    if ($handle) {
                        $batch = [];
                        $chunkSize = 500;

                        while (($line = fgets($handle)) !== false) {
                            $line = trim($line);
                            if ($line === '') continue;

                            $row = json_decode($line, true);
                            if (!is_array($row) || empty($row['salesforce_id'])) continue;

                            $batch[] = [
                                'salesforce_id' => $row['salesforce_id'],
                                'Custom_Owner__c' => !empty($row['Custom_Owner__c']) ? substr(trim($row['Custom_Owner__c']), 0, 255) : null,
                            ];

                            if (count($batch) >= $chunkSize) {
                                DB::table('salesforce_contacts')->upsert(
                                    $batch,
                                    ['salesforce_id'],
                                    ['Custom_Owner__c']
                                );
                                $batch = [];
                            }
                        }

                        if (!empty($batch)) {
                            DB::table('salesforce_contacts')->upsert(
                                $batch,
                                ['salesforce_id'],
                                ['Custom_Owner__c']
                            );
                            $batch = [];
                        }

                        fclose($handle);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('salesforce_contacts')) {
            Schema::table('salesforce_contacts', function (Blueprint $table) {
                if (Schema::hasColumn('salesforce_contacts', 'Custom_Owner__c')) {
                    $table->dropColumn('Custom_Owner__c');
                }
            });
        }
    }
};
