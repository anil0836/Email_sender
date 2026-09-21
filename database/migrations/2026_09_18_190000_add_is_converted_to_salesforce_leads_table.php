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
        if (Schema::hasTable('salesforce_leads')) {
            Schema::table('salesforce_leads', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_leads', 'is_converted')) {
                    $table->boolean('is_converted')->default(false)->after('status')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('salesforce_leads')) {
            Schema::table('salesforce_leads', function (Blueprint $table) {
                if (Schema::hasColumn('salesforce_leads', 'is_converted')) {
                    $table->dropColumn('is_converted');
                }
            });
        }
    }
};
