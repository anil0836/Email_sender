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
        // 1. Add salesforce_sf_user_id foreign key column to salesforce_contacts
        if (Schema::hasTable('salesforce_contacts')) {
            Schema::table('salesforce_contacts', function (Blueprint $table) {
                if (!Schema::hasColumn('salesforce_contacts', 'salesforce_sf_user_id')) {
                    $table->unsignedBigInteger('salesforce_sf_user_id')->nullable()->after('Custom_Owner__c')->index();
                    $table->foreign('salesforce_sf_user_id')
                        ->references('id')
                        ->on('salesforce_sf_users')
                        ->nullOnDelete();
                }
            });

            // 2. Link Custom_Owner__c to salesforce_sf_users to populate owner_email, owner_name, prime_owner_id, salesforce_sf_user_id
            $sfUsers = DB::table('salesforce_sf_users')->get();
            $byName = [];
            $byEmpName = [];

            foreach ($sfUsers as $u) {
                if (!empty(trim((string)$u->name))) {
                    $byName[strtolower(trim((string)$u->name))] = $u;
                }
                if (!empty(trim((string)$u->emp_name))) {
                    $byEmpName[strtolower(trim((string)$u->emp_name))] = $u;
                }
            }

            $contacts = DB::table('salesforce_contacts')
                ->whereNotNull('Custom_Owner__c')
                ->where('Custom_Owner__c', '!=', '')
                ->get(['id', 'Custom_Owner__c']);

            $groupedUpdates = [];

            foreach ($contacts as $c) {
                $rawCustom = strtolower(trim((string)$c->Custom_Owner__c));
                $matchedUser = $byName[$rawCustom] ?? ($byEmpName[$rawCustom] ?? null);

                if ($matchedUser) {
                    $ownerName = $matchedUser->name ?: $matchedUser->emp_name;
                    $ownerEmail = $matchedUser->emp_email;
                    $sfUserId = $matchedUser->id;
                    $primeOwnerId = $matchedUser->salesforce_id;

                    $key = "{$sfUserId}|{$primeOwnerId}|{$ownerName}|{$ownerEmail}";
                    $groupedUpdates[$key]['sf_user_id'] = $sfUserId;
                    $groupedUpdates[$key]['prime_owner_id'] = $primeOwnerId;
                    $groupedUpdates[$key]['owner_name'] = $ownerName;
                    $groupedUpdates[$key]['owner_email'] = $ownerEmail;
                    $groupedUpdates[$key]['ids'][] = $c->id;
                }
            }

            foreach ($groupedUpdates as $updateData) {
                foreach (array_chunk($updateData['ids'], 1000) as $idChunk) {
                    DB::table('salesforce_contacts')
                        ->whereIn('id', $idChunk)
                        ->update([
                            'salesforce_sf_user_id' => $updateData['sf_user_id'],
                            'prime_owner_id' => $updateData['prime_owner_id'],
                            'owner_name' => $updateData['owner_name'],
                            'owner_email' => $updateData['owner_email'],
                        ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('salesforce_contacts')) {
            Schema::table('salesforce_contacts', function (Blueprint $table) {
                if (Schema::hasColumn('salesforce_contacts', 'salesforce_sf_user_id')) {
                    $table->dropForeign(['salesforce_sf_user_id']);
                    $table->dropColumn('salesforce_sf_user_id');
                }
            });
        }
    }
};
