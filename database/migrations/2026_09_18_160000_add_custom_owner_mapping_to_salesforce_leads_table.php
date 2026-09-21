<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add salesforce_sf_user_id and ensure custom_owner index in salesforce_leads
        if (Schema::hasTable('salesforce_leads')) {
            Schema::table('salesforce_leads', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_leads', 'custom_owner')) {
                    $table->string('custom_owner', 255)->nullable()->after('secondary_owner')->index();
                }

                if (!Schema::hasColumn('salesforce_leads', 'salesforce_sf_user_id')) {
                    $table->unsignedBigInteger('salesforce_sf_user_id')->nullable()->after('custom_owner')->index();
                    $table->foreign('salesforce_sf_user_id')
                        ->references('id')
                        ->on('salesforce_sf_users')
                        ->nullOnDelete();
                }
            });
        }

        // 2. Add owners_mapped and owners_not_mapped metrics to salesforce_sync_logs
        if (Schema::hasTable('salesforce_sync_logs')) {
            Schema::table('salesforce_sync_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_sync_logs', 'owners_mapped')) {
                    $table->unsignedInteger('owners_mapped')->default(0)->after('records_failed');
                }
                if (!Schema::hasColumn('salesforce_sync_logs', 'owners_not_mapped')) {
                    $table->unsignedInteger('owners_not_mapped')->default(0)->after('owners_mapped');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('salesforce_sync_logs')) {
            Schema::table('salesforce_sync_logs', function (Blueprint $table) {
                if (Schema::hasColumn('salesforce_sync_logs', 'owners_not_mapped')) {
                    $table->dropColumn('owners_not_mapped');
                }
                if (Schema::hasColumn('salesforce_sync_logs', 'owners_mapped')) {
                    $table->dropColumn('owners_mapped');
                }
            });
        }

        if (Schema::hasTable('salesforce_leads')) {
            Schema::table('salesforce_leads', function (Blueprint $table) {
                if (Schema::hasColumn('salesforce_leads', 'salesforce_sf_user_id')) {
                    $table->dropForeign(['salesforce_sf_user_id']);
                    $table->dropColumn('salesforce_sf_user_id');
                }
            });
        }
    }
};
