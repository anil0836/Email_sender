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
        // 1. Add object_type to salesforce_sync_logs if missing
        if (Schema::hasTable('salesforce_sync_logs')) {
            Schema::table('salesforce_sync_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_sync_logs', 'object_type')) {
                    $table->string('object_type', 50)->default('Lead')->after('id')->index();
                }
            });
        }

        // 2. Salesforce Users Table
        if (!Schema::hasTable('salesforce_users')) {
            Schema::create('salesforce_users', function (Blueprint $table) {
                $table->id();
                $table->string('salesforce_id', 50)->unique();
                $table->string('username', 255)->nullable()->index();
                $table->string('first_name', 150)->nullable();
                $table->string('last_name', 150)->nullable();
                $table->string('name', 255)->nullable()->index();
                $table->string('email', 255)->nullable()->index();
                $table->string('title', 255)->nullable();
                $table->string('department', 150)->nullable()->index();
                $table->string('company_name', 255)->nullable();
                $table->string('division', 150)->nullable();
                $table->string('phone', 100)->nullable();
                $table->string('mobile_phone', 100)->nullable();
                $table->string('city', 150)->nullable();
                $table->string('state', 150)->nullable();
                $table->string('country', 150)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->string('user_type', 100)->nullable()->index();
                $table->string('profile_id', 50)->nullable()->index();
                $table->string('user_role_id', 50)->nullable()->index();
                $table->dateTime('salesforce_created_at')->nullable()->index();
                $table->dateTime('salesforce_updated_at')->nullable()->index();
                $table->dateTime('synced_at')->nullable()->index();
                $table->json('raw_data')->nullable();
                $table->timestamps();
            });
        }

        // 3. Salesforce Accounts Table
        if (!Schema::hasTable('salesforce_accounts')) {
            Schema::create('salesforce_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('salesforce_id', 50)->unique();
                $table->string('name', 255)->index();
                $table->string('type', 100)->nullable()->index();
                $table->string('industry', 150)->nullable()->index();
                $table->string('phone', 100)->nullable();
                $table->string('website', 255)->nullable();
                $table->text('billing_street')->nullable();
                $table->string('billing_city', 150)->nullable()->index();
                $table->string('billing_state', 150)->nullable();
                $table->string('billing_postal_code', 50)->nullable();
                $table->string('billing_country', 150)->nullable()->index();
                $table->text('shipping_street')->nullable();
                $table->string('shipping_city', 150)->nullable();
                $table->string('shipping_state', 150)->nullable();
                $table->string('shipping_postal_code', 50)->nullable();
                $table->string('shipping_country', 150)->nullable();
                $table->unsignedInteger('number_of_employees')->nullable();
                $table->string('owner_id', 50)->nullable()->index();
                $table->string('parent_id', 50)->nullable()->index();
                $table->dateTime('salesforce_created_at')->nullable()->index();
                $table->dateTime('salesforce_updated_at')->nullable()->index();
                $table->dateTime('synced_at')->nullable()->index();
                $table->json('raw_data')->nullable();
                $table->timestamps();
            });
        }

        // 4. Salesforce Contacts Table
        if (!Schema::hasTable('salesforce_contacts')) {
            Schema::create('salesforce_contacts', function (Blueprint $table) {
                $table->id();
                $table->string('salesforce_id', 50)->unique();
                $table->string('account_id', 50)->nullable()->index();
                $table->string('first_name', 150)->nullable();
                $table->string('last_name', 150)->nullable();
                $table->string('name', 255)->nullable()->index();
                $table->string('title', 255)->nullable();
                $table->string('department', 150)->nullable()->index();
                $table->string('email', 255)->nullable()->index();
                $table->string('phone', 100)->nullable();
                $table->string('mobile_phone', 100)->nullable();
                $table->string('lead_source', 150)->nullable()->index();
                $table->text('mailing_street')->nullable();
                $table->string('mailing_city', 150)->nullable()->index();
                $table->string('mailing_state', 150)->nullable();
                $table->string('mailing_postal_code', 50)->nullable();
                $table->string('mailing_country', 150)->nullable()->index();
                $table->string('owner_id', 50)->nullable()->index();
                $table->dateTime('salesforce_created_at')->nullable()->index();
                $table->dateTime('salesforce_updated_at')->nullable()->index();
                $table->dateTime('synced_at')->nullable()->index();
                $table->json('raw_data')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salesforce_contacts');
        Schema::dropIfExists('salesforce_accounts');
        Schema::dropIfExists('salesforce_users');

        if (Schema::hasTable('salesforce_sync_logs')) {
            Schema::table('salesforce_sync_logs', function (Blueprint $table) {
                if (Schema::hasColumn('salesforce_sync_logs', 'object_type')) {
                    $table->dropColumn('object_type');
                }
            });
        }
    }
};
