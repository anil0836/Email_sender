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
        if (Schema::hasTable('salesforce_leads')) {
            Schema::table('salesforce_leads', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_leads', 'Deal_Category__c')) {
                    $table->string('Deal_Category__c', 255)->nullable()->after('industry')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('salesforce_leads')) {
            Schema::table('salesforce_leads', function (Blueprint $table) {
                if (Schema::hasColumn('salesforce_leads', 'Deal_Category__c')) {
                    $table->dropIndex(['Deal_Category__c']);
                    $table->dropColumn('Deal_Category__c');
                }
            });
        }
    }
};
