<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('global_suppression', function (Blueprint $table) {
            if (!Schema::hasColumn('global_suppression', 'normalized_email')) {
                $table->string('normalized_email', 255)->nullable()->after('email');
            }
            if (!Schema::hasColumn('global_suppression', 'status')) {
                $table->string('status', 20)->default('suppressed')->after('reason')->index();
            }
            if (!Schema::hasColumn('global_suppression', 'external_event_id')) {
                $table->string('external_event_id', 150)->nullable()->after('provider_message_id')->index();
            }
            if (!Schema::hasColumn('global_suppression', 'resubscribed_at')) {
                $table->timestamp('resubscribed_at')->nullable()->after('metadata');
            }
            if (!Schema::hasColumn('global_suppression', 'resubscribed_by')) {
                $table->foreignId('resubscribed_by')->nullable()->after('resubscribed_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('global_suppression', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('resubscribed_by')->constrained('users')->nullOnDelete();
            }
        });

        // Backfill existing rows with normalized email
        DB::table('global_suppression')
            ->whereNull('normalized_email')
            ->orWhere('normalized_email', '')
            ->update([
                'normalized_email' => DB::raw('LOWER(TRIM(email))')
            ]);

        // Enforce index on normalized_email
        Schema::table('global_suppression', function (Blueprint $table) {
            $table->string('normalized_email', 255)->nullable(false)->change();
            $table->unique('normalized_email', 'global_suppression_normalized_email_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_suppression', function (Blueprint $table) {
            $table->dropForeign(['resubscribed_by']);
            $table->dropForeign(['created_by']);
            $table->dropIndex(['status']);
            $table->dropUnique('global_suppression_normalized_email_unique');
            $table->dropColumn([
                'normalized_email',
                'status',
                'external_event_id',
                'resubscribed_at',
                'resubscribed_by',
                'created_by'
            ]);
        });
    }
};
