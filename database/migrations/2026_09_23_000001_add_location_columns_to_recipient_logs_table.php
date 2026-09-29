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
        if (Schema::hasTable('recipient_logs')) {
            Schema::table('recipient_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('recipient_logs', 'country')) {
                    $table->string('country', 100)->nullable()->after('error_message');
                }
                if (!Schema::hasColumn('recipient_logs', 'region')) {
                    $table->string('region', 150)->nullable()->after('country');
                }
                if (!Schema::hasColumn('recipient_logs', 'city')) {
                    $table->string('city', 150)->nullable()->after('region');
                }

                $table->index(['campaign_id', 'country']);
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
                $table->dropIndex(['campaign_id', 'country']);
                $table->dropColumn(['country', 'region', 'city']);
            });
        }
    }
};
