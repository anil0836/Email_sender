<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class MigrateFromSqliteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:migrate-from-sqlite {--path= : Absolute or relative path to the SQLite database file} {--truncate : Truncate target MySQL tables before importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely migrates all data from an SQLite database file into the configured MySQL database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sqlitePath = $this->option('path') ?: database_path('database.sqlite');

        if (!file_exists($sqlitePath)) {
            $this->error("SQLite database file not found at: {$sqlitePath}");
            return Command::FAILURE;
        }

        $this->info("Connecting to SQLite database at: {$sqlitePath}");
        $sqlitePdo = new PDO("sqlite:{$sqlitePath}");
        $sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Ordered list of application tables to migrate
        $tables = [
            'servers',
            'sending_domains',
            'users',
            'global_suppression',
            'crm_settings',
            'salesforce_mock_records',
            'salesforce_cache',
            'user_signatures',
            'campaign_templates',
            'audit_logs',
            'campaigns',
            'recipient_logs',
            'recipient_opens',
            'recipient_clicks',
            'inbound_replies',
            'sessions',
            'password_reset_tokens',
        ];

        $shouldTruncate = $this->option('truncate');

        $this->info("Starting data transfer to MySQL (" . config('database.connections.mysql.database') . ")...");

        // Temporarily disable foreign key checks during import
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            $totalImported = 0;

            foreach ($tables as $tableName) {
                // Check if table exists in SQLite
                $checkTable = $sqlitePdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$tableName}'")->fetchColumn();
                if (!$checkTable) {
                    $this->line(" - Table <comment>{$tableName}</comment> not found in SQLite (skipped).");
                    continue;
                }

                if ($shouldTruncate) {
                    DB::table($tableName)->truncate();
                }

                $rows = $sqlitePdo->query("SELECT * FROM \"{$tableName}\"")->fetchAll(PDO::FETCH_ASSOC);
                $count = count($rows);

                if ($count === 0) {
                    $this->line(" - Table <comment>{$tableName}</comment>: 0 records found in SQLite.");
                    continue;
                }

                // Chunk inserts to prevent MySQL packet overflow
                $chunks = array_chunk($rows, 200);
                foreach ($chunks as $chunk) {
                    DB::table($tableName)->insert($chunk);
                }

                $this->info(" ✔ Table <comment>{$tableName}</comment>: successfully imported <info>{$count}</info> records.");
                $totalImported += $count;
            }

            $this->info("\nData migration completed successfully! Total records imported: {$totalImported}");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("\nData migration failed: " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            // Always restore foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
}
