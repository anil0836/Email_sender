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
        // 1. Salesforce Custom Users (SF_User__c) Table
        if (!Schema::hasTable('salesforce_sf_users')) {
            Schema::create('salesforce_sf_users', function (Blueprint $table) {
                $table->id();
                $table->string('salesforce_id', 50)->unique();
                $table->string('name', 255)->nullable()->index(); // Short Name
                $table->string('emp_name', 255)->nullable()->index(); // Emp Name (US)
                $table->string('full_name_in', 255)->nullable()->index(); // Full Name (IN)
                $table->string('emp_email', 255)->nullable()->index();
                $table->string('emp_code', 50)->nullable()->index();
                $table->string('process', 150)->nullable()->index(); // Department / Process
                $table->string('team', 150)->nullable()->index();
                $table->string('location', 150)->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->date('doj')->nullable(); // Date of Joining
                $table->date('dol')->nullable(); // Date of Leaving
                $table->date('dob')->nullable(); // Date of Birth
                $table->string('phone', 100)->nullable();
                $table->string('mobile', 100)->nullable();
                $table->string('linkedin', 255)->nullable();
                $table->string('owner_id', 50)->nullable()->index(); // Standard User ID
                $table->dateTime('salesforce_created_at')->nullable()->index();
                $table->dateTime('salesforce_updated_at')->nullable()->index();
                $table->dateTime('synced_at')->nullable()->index();
                $table->json('raw_data')->nullable();
                $table->timestamps();
            });
        }

        // 2. Add Prime_Owner__c, Secondary_Owner__c, Custom_Owner__c to salesforce_leads
        if (Schema::hasTable('salesforce_leads')) {
            Schema::table('salesforce_leads', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_leads', 'prime_owner_id')) {
                    $table->string('prime_owner_id', 50)->nullable()->after('owner_id')->index();
                }
                if (!Schema::hasColumn('salesforce_leads', 'secondary_owner')) {
                    $table->string('secondary_owner', 150)->nullable()->after('prime_owner_id');
                }
                if (!Schema::hasColumn('salesforce_leads', 'custom_owner')) {
                    $table->string('custom_owner', 150)->nullable()->after('secondary_owner');
                }
            });
        }

        // 3. Add Prime_Owner__c, Secondary_Owner__c, Custom_Owner__c to salesforce_accounts
        if (Schema::hasTable('salesforce_accounts')) {
            Schema::table('salesforce_accounts', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_accounts', 'prime_owner_id')) {
                    $table->string('prime_owner_id', 50)->nullable()->after('owner_id')->index();
                }
                if (!Schema::hasColumn('salesforce_accounts', 'secondary_owner')) {
                    $table->string('secondary_owner', 150)->nullable()->after('prime_owner_id');
                }
                if (!Schema::hasColumn('salesforce_accounts', 'custom_owner')) {
                    $table->string('custom_owner', 150)->nullable()->after('secondary_owner');
                }
            });
        }

        // 4. Add Prime_Owner__c, Secondary_Owner__c to salesforce_contacts
        if (Schema::hasTable('salesforce_contacts')) {
            Schema::table('salesforce_contacts', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_contacts', 'prime_owner_id')) {
                    $table->string('prime_owner_id', 50)->nullable()->after('owner_id')->index();
                }
                if (!Schema::hasColumn('salesforce_contacts', 'secondary_owner')) {
                    $table->string('secondary_owner', 150)->nullable()->after('prime_owner_id');
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
                $table->dropColumn(['prime_owner_id', 'secondary_owner']);
            });
        }

        if (Schema::hasTable('salesforce_accounts')) {
            Schema::table('salesforce_accounts', function (Blueprint $table) {
                $table->dropColumn(['prime_owner_id', 'secondary_owner', 'custom_owner']);
            });
        }

        if (Schema::hasTable('salesforce_leads')) {
            Schema::table('salesforce_leads', function (Blueprint $table) {
                $table->dropColumn(['prime_owner_id', 'secondary_owner', 'custom_owner']);
            });
        }

        Schema::dropIfExists('salesforce_sf_users');
    }
};
