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
        // 1. Enhance user_signatures
        Schema::table('user_signatures', function (Blueprint $table) {
            $table->longText('content')->nullable()->change();
            $table->string('sender_name')->nullable()->after('name');
            $table->string('job_title')->nullable()->after('sender_name');
            $table->string('company_name')->nullable()->after('job_title');
            $table->string('email')->nullable()->after('company_name');
            $table->string('phone')->nullable()->after('email');
            $table->string('website')->nullable()->after('phone');
            $table->text('address')->nullable()->after('website');
            $table->boolean('is_default')->default(false)->after('content');
            $table->boolean('is_active')->default(true)->after('is_default');
        });

        // 2. Enhance campaign_templates
        Schema::table('campaign_templates', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('body');
            $table->boolean('is_active')->default(true)->after('is_default');
        });

        // 3. Enhance campaigns
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('user_id')->constrained('campaign_templates')->nullOnDelete();
            $table->foreignId('signature_id')->nullable()->after('template_id')->constrained('user_signatures')->nullOnDelete();
            $table->longText('signature_snapshot')->nullable()->after('body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropForeign(['signature_id']);
            $table->dropColumn(['template_id', 'signature_id', 'signature_snapshot']);
        });

        Schema::table('campaign_templates', function (Blueprint $table) {
            $table->dropColumn(['is_default', 'is_active']);
        });

        Schema::table('user_signatures', function (Blueprint $table) {
            $table->dropColumn([
                'sender_name',
                'job_title',
                'company_name',
                'email',
                'phone',
                'website',
                'address',
                'is_default',
                'is_active',
            ]);
        });
    }
};
