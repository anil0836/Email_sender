<?php

use App\Models\Campaign;
use App\Models\SalesforceSfUser;
use App\Models\SalesforceUser;
use App\Models\User;
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
        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'team')) {
                $table->string('team', 100)->nullable()->after('user_id')->index();
            }
            if (!Schema::hasColumn('campaigns', 'manager_salesforce_id')) {
                $table->string('manager_salesforce_id', 50)->nullable()->after('team')->index();
            }
            if (!Schema::hasColumn('campaigns', 'manager_user_id')) {
                $table->unsignedBigInteger('manager_user_id')->nullable()->after('manager_salesforce_id')->index();
                $table->foreign('manager_user_id')->references('id')->on('users')->nullOnDelete();
            }
        });

        // Safe backfill for existing campaigns
        try {
            $campaigns = DB::table('campaigns')->whereNull('team')->get();
            foreach ($campaigns as $camp) {
                if (!$camp->user_id) {
                    continue;
                }

                $user = DB::table('users')->where('id', $camp->user_id)->first();
                if (!$user) {
                    continue;
                }

                // 1. Resolve Team from salesforce_sf_users
                $team = null;
                $sfUser = DB::table('salesforce_sf_users')
                    ->where(function ($q) use ($user) {
                        $q->where('emp_email', $user->email)
                          ->orWhere('name', $user->username)
                          ->orWhere('emp_code', $user->emp_id);
                    })
                    ->whereNotNull('team')
                    ->where('team', '!=', '')
                    ->first();

                if ($sfUser) {
                    $team = $sfUser->team;
                } else {
                    // Check if the user itself is a manager (salesforce_users.first_name)
                    $asManager = DB::table('salesforce_users')
                        ->where(function ($q) use ($user) {
                            $q->where('email', $user->email)
                              ->orWhere('username', $user->username)
                              ->orWhere('first_name', $user->username);
                        })
                        ->whereNotNull('first_name')
                        ->first();

                    if ($asManager) {
                        $teamExists = DB::table('salesforce_sf_users')->where('team', $asManager->first_name)->exists();
                        if ($teamExists) {
                            $team = $asManager->first_name;
                        }
                    }
                }

                // Fallback to user's assigned manager's team or name if available
                if (!$team && $user->manager_id) {
                    $localManager = DB::table('users')->where('id', $user->manager_id)->first();
                    if ($localManager) {
                        $team = $localManager->name ?: $localManager->username;
                    }
                }

                // 2. Resolve Team Manager from salesforce_users where first_name = team
                $managerSfId = null;
                $managerUserId = null;

                if ($team) {
                    $sfManager = DB::table('salesforce_users')->where('first_name', $team)->first();
                    if ($sfManager) {
                        $managerSfId = $sfManager->salesforce_id;

                        // Find local user matching manager
                        $localMgrUser = DB::table('users')
                            ->where('email', $sfManager->email)
                            ->orWhere('username', $sfManager->username)
                            ->orWhere('username', $sfManager->first_name)
                            ->first();

                        if ($localMgrUser) {
                            $managerUserId = $localMgrUser->id;
                        }
                    }
                }

                if (!$managerUserId && $user->manager_id) {
                    $managerUserId = $user->manager_id;
                }

                DB::table('campaigns')->where('id', $camp->id)->update([
                    'team' => $team,
                    'manager_salesforce_id' => $managerSfId,
                    'manager_user_id' => $managerUserId,
                ]);
            }
        } catch (\Throwable $e) {
            // Log or ignore backfill errors during migration
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'manager_user_id')) {
                $table->dropForeign(['manager_user_id']);
                $table->dropColumn('manager_user_id');
            }
            if (Schema::hasColumn('campaigns', 'manager_salesforce_id')) {
                $table->dropColumn('manager_salesforce_id');
            }
            if (Schema::hasColumn('campaigns', 'team')) {
                $table->dropColumn('team');
            }
        });
    }
};
