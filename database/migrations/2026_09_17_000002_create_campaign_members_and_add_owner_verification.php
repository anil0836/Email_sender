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
        // 1. Create campaign_members table
        if (!Schema::hasTable('campaign_members')) {
            Schema::create('campaign_members', function (Blueprint $table) {
                $table->id();
                $table->uuid('campaign_id');
                $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
                $table->string('record_type', 50)->index(); // Lead, Contact, Account
                $table->unsignedBigInteger('local_record_id')->nullable()->index();
                $table->string('salesforce_record_id', 50)->index();
                $table->string('email', 255)->nullable()->index();
                $table->string('name', 255)->nullable()->index();
                $table->string('company', 255)->nullable()->index();
                $table->string('salesforce_owner_id', 50)->nullable()->index();
                $table->string('prime_owner_id', 50)->nullable()->index();
                $table->string('owner_name', 255)->nullable();
                $table->string('owner_email', 255)->nullable();
                $table->string('owner_verification_status', 50)->default('verified')->index(); // verified, changed, unverified, pending
                $table->dateTime('last_owner_verified_at')->nullable();
                $table->string('status', 50)->default('queued')->index(); // approved, blocked, queued, sent, delivered, failed
                $table->timestamps();

                $table->index(['campaign_id', 'record_type']);
                $table->index(['campaign_id', 'owner_verification_status']);
            });
        }

        // 2. Add owner verification columns to salesforce_leads
        if (Schema::hasTable('salesforce_leads')) {
            Schema::table('salesforce_leads', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_leads', 'owner_name')) {
                    $table->string('owner_name', 255)->nullable()->after('custom_owner');
                }
                if (!Schema::hasColumn('salesforce_leads', 'owner_email')) {
                    $table->string('owner_email', 255)->nullable()->after('owner_name');
                }
                if (!Schema::hasColumn('salesforce_leads', 'owner_verification_status')) {
                    $table->string('owner_verification_status', 50)->default('verified')->after('owner_email')->index();
                }
                if (!Schema::hasColumn('salesforce_leads', 'last_owner_verified_at')) {
                    $table->dateTime('last_owner_verified_at')->nullable()->after('owner_verification_status');
                }
                if (!Schema::hasColumn('salesforce_leads', 'previous_owner_id')) {
                    $table->string('previous_owner_id', 50)->nullable()->after('last_owner_verified_at');
                }
            });
        }

        // 3. Add owner verification columns to salesforce_contacts
        if (Schema::hasTable('salesforce_contacts')) {
            Schema::table('salesforce_contacts', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_contacts', 'owner_name')) {
                    $table->string('owner_name', 255)->nullable()->after('secondary_owner');
                }
                if (!Schema::hasColumn('salesforce_contacts', 'owner_email')) {
                    $table->string('owner_email', 255)->nullable()->after('owner_name');
                }
                if (!Schema::hasColumn('salesforce_contacts', 'owner_verification_status')) {
                    $table->string('owner_verification_status', 50)->default('verified')->after('owner_email')->index();
                }
                if (!Schema::hasColumn('salesforce_contacts', 'last_owner_verified_at')) {
                    $table->dateTime('last_owner_verified_at')->nullable()->after('owner_verification_status');
                }
                if (!Schema::hasColumn('salesforce_contacts', 'previous_owner_id')) {
                    $table->string('previous_owner_id', 50)->nullable()->after('last_owner_verified_at');
                }
            });
        }

        // 4. Add owner verification columns to salesforce_accounts
        if (Schema::hasTable('salesforce_accounts')) {
            Schema::table('salesforce_accounts', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_accounts', 'owner_name')) {
                    $table->string('owner_name', 255)->nullable()->after('custom_owner');
                }
                if (!Schema::hasColumn('salesforce_accounts', 'owner_email')) {
                    $table->string('owner_email', 255)->nullable()->after('owner_name');
                }
                if (!Schema::hasColumn('salesforce_accounts', 'owner_verification_status')) {
                    $table->string('owner_verification_status', 50)->default('verified')->after('owner_email')->index();
                }
                if (!Schema::hasColumn('salesforce_accounts', 'last_owner_verified_at')) {
                    $table->dateTime('last_owner_verified_at')->nullable()->after('owner_verification_status');
                }
                if (!Schema::hasColumn('salesforce_accounts', 'previous_owner_id')) {
                    $table->string('previous_owner_id', 50)->nullable()->after('last_owner_verified_at');
                }
            });
        }

        // 5. Add campaign_member_id to recipient_logs
        if (Schema::hasTable('recipient_logs')) {
            Schema::table('recipient_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('recipient_logs', 'campaign_member_id')) {
                    $table->foreignId('campaign_member_id')->nullable()->after('campaign_id')->constrained('campaign_members')->nullOnDelete();
                }
                if (!Schema::hasColumn('recipient_logs', 'owner_verification_status')) {
                    $table->string('owner_verification_status', 50)->default('verified')->after('record_owner_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('recipient_logs')) {
            Schema::table('recipient_logs', function (Blueprint $table) {
                if (Schema::hasColumn('recipient_logs', 'campaign_member_id')) {
                    $table->dropForeign(['campaign_member_id']);
                    $table->dropColumn(['campaign_member_id', 'owner_verification_status']);
                }
            });
        }

        if (Schema::hasTable('salesforce_accounts')) {
            Schema::table('salesforce_accounts', function (Blueprint $table) {
                $table->dropColumn([
                    'owner_name',
                    'owner_email',
                    'owner_verification_status',
                    'last_owner_verified_at',
                    'previous_owner_id'
                ]);
            });
        }

        if (Schema::hasTable('salesforce_contacts')) {
            Schema::table('salesforce_contacts', function (Blueprint $table) {
                $table->dropColumn([
                    'owner_name',
                    'owner_email',
                    'owner_verification_status',
                    'last_owner_verified_at',
                    'previous_owner_id'
                ]);
            });
        }

        if (Schema::hasTable('salesforce_leads')) {
            Schema::table('salesforce_leads', function (Blueprint $table) {
                $table->dropColumn([
                    'owner_name',
                    'owner_email',
                    'owner_verification_status',
                    'last_owner_verified_at',
                    'previous_owner_id'
                ]);
            });
        }

        Schema::dropIfExists('campaign_members');
    }
};
