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
        // 1. Salesforce Leads Storage Table
        if (!Schema::hasTable('salesforce_leads')) {
            Schema::create('salesforce_leads', function (Blueprint $table) {
                $table->id();
                $table->string('salesforce_id', 50)->unique();
                $table->string('first_name', 150)->nullable();
                $table->string('last_name', 150)->nullable();
                $table->string('name', 255)->nullable()->index();
                $table->string('company', 255)->nullable()->index();
                $table->string('title', 255)->nullable();
                $table->string('email', 255)->nullable()->index();
                $table->string('phone', 100)->nullable();
                $table->string('mobile_phone', 100)->nullable();
                $table->string('website', 255)->nullable();
                $table->string('lead_source', 150)->nullable()->index();
                $table->string('industry', 150)->nullable()->index();
                $table->string('status', 100)->nullable()->index();
                $table->text('street')->nullable();
                $table->string('city', 150)->nullable()->index();
                $table->string('state', 150)->nullable();
                $table->string('postal_code', 50)->nullable();
                $table->string('country', 150)->nullable();
                $table->string('owner_id', 50)->nullable()->index();
                $table->dateTime('salesforce_created_at')->nullable()->index();
                $table->dateTime('salesforce_updated_at')->nullable()->index();
                $table->dateTime('synced_at')->nullable()->index();
                $table->json('raw_data')->nullable();
                $table->timestamps();
            });
        }

        // 2. Salesforce Synchronization Tracking & Audit Logs Table
        if (!Schema::hasTable('salesforce_sync_logs')) {
            Schema::create('salesforce_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->string('sync_type', 50)->default('incremental'); // incremental, full, manual, cron
                $table->string('status', 50)->default('running'); // running, success, failed
                $table->dateTime('started_at');
                $table->dateTime('completed_at')->nullable();
                $table->unsignedInteger('records_fetched')->default(0);
                $table->unsignedInteger('records_created')->default(0);
                $table->unsignedInteger('records_updated')->default(0);
                $table->unsignedInteger('records_skipped')->default(0);
                $table->unsignedInteger('records_failed')->default(0);
                $table->dateTime('last_modified_checkpoint')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salesforce_sync_logs');
        Schema::dropIfExists('salesforce_leads');
    }
};
