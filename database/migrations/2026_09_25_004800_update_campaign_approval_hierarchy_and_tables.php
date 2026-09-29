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
        // 1. Data correction: Assign Marcus (id=4, line_manager) to Manager Mark (id=2)
        if (Schema::hasTable('users')) {
            DB::table('users')
                ->where('id', 4)
                ->where('role', 'line_manager')
                ->whereNull('manager_id')
                ->update(['manager_id' => 2]);
        }

        // 2. Add approval & rejection tracking columns to campaigns table
        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                if (!Schema::hasColumn('campaigns', 'line_manager_id')) {
                    $table->unsignedBigInteger('line_manager_id')->nullable()->after('manager_user_id')->index();
                }
                if (!Schema::hasColumn('campaigns', 'current_approver_id')) {
                    $table->unsignedBigInteger('current_approver_id')->nullable()->after('status')->index();
                }
                if (!Schema::hasColumn('campaigns', 'rejected_by')) {
                    $table->unsignedBigInteger('rejected_by')->nullable()->after('approval_at')->index();
                }
                if (!Schema::hasColumn('campaigns', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('rejected_by');
                }
                if (!Schema::hasColumn('campaigns', 'rejected_at')) {
                    $table->dateTime('rejected_at')->nullable()->after('rejection_reason');
                }
            });
        }

        // 3. Create campaign_approvals audit table
        if (!Schema::hasTable('campaign_approvals')) {
            Schema::create('campaign_approvals', function (Blueprint $table) {
                $table->id();
                $table->uuid('campaign_id')->index();
                $table->unsignedBigInteger('approver_id')->nullable()->index();
                $table->string('approver_role', 50)->nullable();
                $table->string('action', 50); // submitted, approved, rejected
                $table->string('previous_status', 50)->nullable();
                $table->string('new_status', 50);
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->foreign('campaign_id')
                    ->references('id')
                    ->on('campaigns')
                    ->cascadeOnDelete();

                $table->foreign('approver_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('campaign_approvals')) {
            Schema::dropIfExists('campaign_approvals');
        }

        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                if (Schema::hasColumn('campaigns', 'rejected_at')) {
                    $table->dropColumn('rejected_at');
                }
                if (Schema::hasColumn('campaigns', 'rejection_reason')) {
                    $table->dropColumn('rejection_reason');
                }
                if (Schema::hasColumn('campaigns', 'rejected_by')) {
                    $table->dropColumn('rejected_by');
                }
                if (Schema::hasColumn('campaigns', 'current_approver_id')) {
                    $table->dropColumn('current_approver_id');
                }
                if (Schema::hasColumn('campaigns', 'line_manager_id')) {
                    $table->dropColumn('line_manager_id');
                }
            });
        }
    }
};
