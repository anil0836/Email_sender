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
        // 1. Servers table
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->integer('port');
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('sending_ip');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Sending Domains table
        Schema::create('sending_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain_name')->unique();
            $table->string('status')->default('enabled'); // enabled, disabled
            $table->string('spf_status')->default('unverified'); // verified, unverified
            $table->string('dkim_status')->default('unverified'); // verified, unverified
            $table->string('dmarc_status')->default('unverified'); // verified, unverified
            $table->foreignId('server_id')->nullable()->constrained('servers')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->integer('rate_limit_per_hour')->default(1000);
            $table->timestamps();
        });

        // 3. Users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('emp_id')->nullable();
            $table->string('username')->unique();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('user'); // admin, manager, user
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_server_id')->nullable()->constrained('servers')->nullOnDelete();
            $table->foreignId('assigned_domain_id')->nullable()->constrained('sending_domains')->nullOnDelete();
            $table->boolean('is_blocked')->default(false);
            $table->integer('daily_limit')->default(1000);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('sending_domains');
        Schema::dropIfExists('servers');
    }
};
