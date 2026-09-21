<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('salesforce_leads')) {
            Schema::table('salesforce_leads', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_leads', 'Custom_Owner__c')) {
                    $table->string('Custom_Owner__c', 255)->nullable()->after('custom_owner')->index();
                }
            });

            // Synchronize existing custom_owner values into Custom_Owner__c
            if (Schema::hasColumn('salesforce_leads', 'custom_owner') && Schema::hasColumn('salesforce_leads', 'Custom_Owner__c')) {
                DB::table('salesforce_leads')
                    ->whereNotNull('custom_owner')
                    ->where(function ($q) {
                        $q->whereNull('Custom_Owner__c')
                          ->orWhere('Custom_Owner__c', '');
                    })
                    ->update([
                        'Custom_Owner__c' => DB::raw('custom_owner')
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('salesforce_leads')) {
            Schema::table('salesforce_leads', function (Blueprint $table) {
                if (Schema::hasColumn('salesforce_leads', 'Custom_Owner__c')) {
                    $table->dropColumn('Custom_Owner__c');
                }
            });
        }
    }
};
