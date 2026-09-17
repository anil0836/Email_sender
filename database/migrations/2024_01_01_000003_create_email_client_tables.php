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
        // 1. Global Suppression table
        Schema::create('global_suppression', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('reason');
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();
        });

        // 2. Campaigns table
        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject');
            $table->longText('body');
            $table->string('sending_domain');
            $table->string('from_address');
            $table->string('reply_to');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('total_requested')->default(0);
            $table->integer('total_approved')->default(0);
            $table->integer('total_blocked')->default(0);
            $table->string('status')->default('draft'); // draft, pending_approval, rejected, queued, sending, completed, scheduled
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approval_remark')->nullable();
            $table->timestamp('approval_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->longText('attachments')->nullable(); // JSON list of attachment filenames
            $table->timestamps();
        });

        // 3. Recipient Logs table
        Schema::create('recipient_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('campaign_id');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->string('email');
            $table->string('salesforce_record_id');
            $table->string('salesforce_object'); // Contact, Lead
            $table->string('record_owner_id');
            $table->string('decision'); // approved, blocked
            $table->string('decision_reason')->nullable(); // EMAIL_OPT_OUT, GLOBAL_SUPPRESSION, DIFFERENT_OWNER, INVALID_EMAIL, INACTIVE_RECORD, MISSING_CONSENT, COMPLIANCE_RULE
            $table->string('delivery_status')->default('queued'); // blocked, queued, sent, delivered, failed, bounce, spam_complaint, opened, unsubscribed, scheduled, pending_approval
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('validated_at')->useCurrent();
            $table->timestamp('sent_at')->nullable();
            $table->string('tracking_token')->unique();
            $table->timestamps();
            
            $table->index(['campaign_id', 'delivery_status']);
            $table->index('tracking_token');
        });

        // 4. Recipient Opens table
        Schema::create('recipient_opens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_log_id')->constrained('recipient_logs')->cascadeOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->timestamps();
        });

        // 5. Recipient Clicks table
        Schema::create('recipient_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_log_id')->constrained('recipient_logs')->cascadeOnDelete();
            $table->timestamp('clicked_at')->useCurrent();
            $table->text('url');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        // 6. Inbound Replies table
        Schema::create('inbound_replies', function (Blueprint $table) {
            $table->id();
            $table->uuid('campaign_id');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->string('recipient_email');
            $table->string('reply_subject');
            $table->longText('reply_body');
            $table->timestamp('received_at')->useCurrent();
            $table->string('mapped_salesforce_record_id')->nullable();
            $table->string('mapped_owner_id')->nullable();
            $table->timestamps();
        });

        // 7. Salesforce Mock Records table
        Schema::create('salesforce_mock_records', function (Blueprint $table) {
            $table->string('id')->primary(); // e.g. 003SF0000000001
            $table->string('object_type'); // Contact, Lead
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('owner_id'); // Username of owner
            $table->boolean('opted_out')->default(false);
            $table->string('status'); // Active, Inactive, New, Working, Disqualified, etc.
            $table->string('consent_status')->default('valid'); // valid, missing, expired
            $table->string('lawful_basis')->nullable();
            $table->boolean('do_not_call')->default(false);
            $table->string('deal_category')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->nullable();
            $table->timestamps();
            
            $table->index('email');
            $table->index('owner_id');
        });

        // 8. Salesforce Cache table
        Schema::create('salesforce_cache', function (Blueprint $table) {
            $table->string('record_id')->primary();
            $table->string('object_type');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('owner_id');
            $table->boolean('opted_out')->default(false);
            $table->string('status');
            $table->string('consent_status');
            $table->string('lawful_basis')->nullable();
            $table->timestamp('cached_at')->useCurrent();
            $table->timestamps();
            
            $table->index('email');
        });

        // 9. CRM Settings table
        Schema::create('crm_settings', function (Blueprint $table) {
            $table->id();
            $table->string('salesforce_client_id')->nullable();
            $table->string('salesforce_client_secret')->nullable();
            $table->string('salesforce_login_url')->default('https://login.salesforce.com');
            $table->string('salesforce_username')->nullable();
            $table->string('salesforce_token_or_password')->nullable();
            $table->boolean('is_mock')->default(true);
            $table->timestamps();
        });

        // 10. User Signatures table
        Schema::create('user_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->longText('content');
            $table->timestamps();
        });

        // 11. Campaign Templates table
        Schema::create('campaign_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('subject');
            $table->longText('body');
            $table->timestamps();
        });

        // 12. Audit Logs table
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('details')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('campaign_templates');
        Schema::dropIfExists('user_signatures');
        Schema::dropIfExists('crm_settings');
        Schema::dropIfExists('salesforce_cache');
        Schema::dropIfExists('salesforce_mock_records');
        Schema::dropIfExists('inbound_replies');
        Schema::dropIfExists('recipient_clicks');
        Schema::dropIfExists('recipient_opens');
        Schema::dropIfExists('recipient_logs');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('global_suppression');
    }
};
