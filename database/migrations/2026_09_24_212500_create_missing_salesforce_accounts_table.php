<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
                $table->string('prime_owner_id', 50)->nullable()->index();
                $table->string('secondary_owner', 150)->nullable();
                $table->string('custom_owner', 150)->nullable();
                $table->string('owner_name', 255)->nullable();
                $table->string('owner_email', 255)->nullable();
                $table->string('owner_verification_status', 50)->default('verified')->index();
                $table->dateTime('last_owner_verified_at')->nullable();
                $table->string('previous_owner_id', 50)->nullable();
                $table->string('parent_id', 50)->nullable()->index();
                $table->dateTime('salesforce_created_at')->nullable()->index();
                $table->dateTime('salesforce_updated_at')->nullable()->index();
                $table->dateTime('synced_at')->nullable()->index();
                $table->json('raw_data')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salesforce_accounts');
    }
};
