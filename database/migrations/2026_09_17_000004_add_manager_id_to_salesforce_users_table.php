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
        if (Schema::hasTable('salesforce_users')) {
            Schema::table('salesforce_users', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_users', 'manager_id')) {
                    $table->string('manager_id', 50)->nullable()->after('user_role_id')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('salesforce_users')) {
            Schema::table('salesforce_users', function (Blueprint $table) {
                if (Schema::hasColumn('salesforce_users', 'manager_id')) {
                    $table->dropColumn('manager_id');
                }
            });
        }
    }
};
