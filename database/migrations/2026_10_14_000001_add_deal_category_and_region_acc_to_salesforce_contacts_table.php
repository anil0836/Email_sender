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
        if (Schema::hasTable('salesforce_contacts')) {
            Schema::table('salesforce_contacts', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_contacts', 'Deal_Category__c')) {
                    $table->string('Deal_Category__c', 255)->nullable()->after('department')->index();
                }
                if (!Schema::hasColumn('salesforce_contacts', 'Region_Acc__c')) {
                    $table->string('Region_Acc__c', 100)->nullable()->after('mailing_country')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('salesforce_contacts')) {
            Schema::table('salesforce_contacts', function (Blueprint $table) {
                if (Schema::hasColumn('salesforce_contacts', 'Deal_Category__c')) {
                    $table->dropIndex(['Deal_Category__c']);
                    $table->dropColumn('Deal_Category__c');
                }
                if (Schema::hasColumn('salesforce_contacts', 'Region_Acc__c')) {
                    $table->dropIndex(['Region_Acc__c']);
                    $table->dropColumn('Region_Acc__c');
                }
            });
        }
    }
};
