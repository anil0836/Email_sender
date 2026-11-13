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
        Schema::table('recipient_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('recipient_logs', 'retry_count')) {
                $table->unsignedInteger('retry_count')->default(0)->after('delivery_status');
            }
            if (!Schema::hasColumn('recipient_logs', 'next_retry_at')) {
                $table->timestamp('next_retry_at')->nullable()->after('retry_count')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipient_logs', function (Blueprint $table) {
            if (Schema::hasColumn('recipient_logs', 'next_retry_at')) {
                $table->dropIndex(['next_retry_at']);
                $table->dropColumn('next_retry_at');
            }
            if (Schema::hasColumn('recipient_logs', 'retry_count')) {
                $table->dropColumn('retry_count');
            }
        });
    }
};
