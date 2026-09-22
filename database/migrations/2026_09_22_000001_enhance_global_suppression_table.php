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
        Schema::table('global_suppression', function (Blueprint $table) {
            if (!Schema::hasColumn('global_suppression', 'source')) {
                $table->string('source', 50)->default('pabbly_webhook')->after('reason');
            }
            if (!Schema::hasColumn('global_suppression', 'campaign_id')) {
                $table->uuid('campaign_id')->nullable()->after('source')->index();
            }
            if (!Schema::hasColumn('global_suppression', 'provider_message_id')) {
                $table->string('provider_message_id', 150)->nullable()->after('campaign_id')->index();
            }
            if (!Schema::hasColumn('global_suppression', 'metadata')) {
                $table->json('metadata')->nullable()->after('provider_message_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_suppression', function (Blueprint $table) {
            $table->dropColumn(['source', 'campaign_id', 'provider_message_id', 'metadata']);
        });
    }
};
