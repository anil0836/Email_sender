<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\SalesforceSfUser;
use App\Models\SalesforceUser;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeamService
{
    /**
     * Resolves the Team name for a given user.
     */
    public function resolveUserTeam(User|int|string|null $user): ?string
    {
        if (!$user) {
            return null;
        }

        $localUser = null;
        $username = null;
        $email = null;
        $empId = null;

        if ($user instanceof User) {
            $localUser = $user;
            $username = $user->username;
            $email = $user->email;
            $empId = $user->emp_id;
        } elseif (is_numeric($user)) {
            $localUser = User::find($user);
            if ($localUser) {
                $username = $localUser->username;
                $email = $localUser->email;
                $empId = $localUser->emp_id;
            }
        } else {
            $username = (string) $user;
            $email = (string) $user;
        }

        // 1. Look up in salesforce_sf_users by email, emp_code, or name
        $sfUser = SalesforceSfUser::where(function ($q) use ($email, $username, $empId) {
            if ($email) {
                $q->where('emp_email', $email);
            }
            if ($username) {
                $q->orWhere('name', $username);
            }
            if ($empId) {
                $q->orWhere('emp_code', $empId);
            }
        })
        ->whereNotNull('team')
        ->where('team', '!=', '')
        ->first();

        if ($sfUser && !empty($sfUser->team)) {
            return trim($sfUser->team);
        }

        // 2. Check if the user is a Manager in salesforce_users where first_name is a team
        $sfManager = SalesforceUser::where(function ($q) use ($email, $username) {
            if ($email) {
                $q->where('email', $email);
            }
            if ($username) {
                $q->orWhere('username', $username)
                  ->orWhere('first_name', $username);
            }
        })
        ->whereNotNull('first_name')
        ->where('first_name', '!=', '')
        ->first();

        if ($sfManager) {
            $teamName = trim($sfManager->first_name);
            $teamExists = SalesforceSfUser::where('team', $teamName)->exists();
            if ($teamExists) {
                return $teamName;
            }
        }

        // 3. Check if username or first word of name directly matches a known team
        if ($username) {
            $directTeam = SalesforceSfUser::where('team', $username)->first();
            if ($directTeam) {
                return $directTeam->team;
            }
        }

        // 4. Fallback to local manager's name/team if present
        if ($localUser && $localUser->manager_id) {
            $manager = User::find($localUser->manager_id);
            if ($manager) {
                $mgrTeam = $this->resolveUserTeam($manager);
                if ($mgrTeam) {
                    return $mgrTeam;
                }
                return $manager->name ?: $manager->username;
            }
        }

        return null;
    }

    /**
     * Resolves Team Manager information given a team name.
     * Team is matched with first_name of salesforce_users.
     */
    public function resolveTeamManager(?string $team): ?array
    {
        if (empty($team)) {
            return null;
        }

        $cleanTeam = trim($team);

        // Match team with first_name in salesforce_users
        $sfManager = SalesforceUser::where('first_name', $cleanTeam)
            ->orWhere('first_name', 'like', $cleanTeam)
            ->orWhere('name', 'like', $cleanTeam . ' %')
            ->first();

        if (!$sfManager) {
            return null;
        }

        // Resolve local User for this Salesforce manager
        $localUser = User::where('email', $sfManager->email)
            ->orWhere('username', $sfManager->username)
            ->orWhere('username', $sfManager->first_name)
            ->first();

        return [
            'salesforce_id' => $sfManager->salesforce_id,
            'name' => $sfManager->name,
            'first_name' => $sfManager->first_name,
            'last_name' => $sfManager->last_name,
            'email' => $sfManager->email,
            'username' => $sfManager->username,
            'local_user_id' => $localUser?->id,
            'local_user' => $localUser,
            'salesforce_user' => $sfManager,
        ];
    }

    /**
     * Returns all team names managed by a given user.
     */
    public function getManagedTeamsForUser(User|int|string|null $user): array
    {
        if (!$user) {
            return [];
        }

        $localUser = $user instanceof User ? $user : User::find($user);
        $role = $localUser ? $localUser->role : null;
        $username = $localUser ? $localUser->username : (string) $user;
        $email = $localUser ? $localUser->email : (string) $user;

        // Admin manages all teams
        if ($role === 'admin') {
            return SalesforceSfUser::whereNotNull('team')
                ->where('team', '!=', '')
                ->distinct()
                ->pluck('team')
                ->toArray();
        }

        $managedTeams = [];

        // 1. Check if user maps to salesforce_users where first_name is a team in salesforce_sf_users
        $sfUsers = SalesforceUser::where(function ($q) use ($email, $username) {
            if ($email) {
                $q->where('email', $email);
            }
            if ($username) {
                $q->orWhere('username', $username)
                  ->orWhere('first_name', $username);
            }
        })->get();

        foreach ($sfUsers as $sfu) {
            if (!empty($sfu->first_name)) {
                $teamExists = SalesforceSfUser::where('team', $sfu->first_name)->exists();
                if ($teamExists && !in_array($sfu->first_name, $managedTeams)) {
                    $managedTeams[] = $sfu->first_name;
                }
            }
        }

        // 2. Direct match by username / first_name in salesforce_sf_users teams
        if ($username) {
            $sfTeams = SalesforceSfUser::where('team', $username)->distinct()->pluck('team')->toArray();
            foreach ($sfTeams as $t) {
                if (!in_array($t, $managedTeams)) {
                    $managedTeams[] = $t;
                }
            }
        }

        // 3. Check direct subordinates via local users table (if role === 'manager' or 'line_manager')
        if ($localUser && in_array($localUser->role, ['manager', 'line_manager'])) {
            $subordinateIds = User::where('manager_id', $localUser->id)->pluck('id')->toArray();
            
            // If manager, also check recursive subordinates (e.g. users under line managers)
            if ($localUser->role === 'manager' && !empty($subordinateIds)) {
                $subLineManagerIds = User::whereIn('id', $subordinateIds)->where('role', 'line_manager')->pluck('id')->toArray();
                if (!empty($subLineManagerIds)) {
                    $subUserIds = User::whereIn('manager_id', $subLineManagerIds)->pluck('id')->toArray();
                    $subordinateIds = array_unique(array_merge($subordinateIds, $subUserIds));
                }
            }

            foreach ($subordinateIds as $subId) {
                $subTeam = $this->resolveUserTeam($subId);
                if ($subTeam && !in_array($subTeam, $managedTeams)) {
                    $managedTeams[] = $subTeam;
                }
            }
            // If still empty, add manager's own team or manager username
            if (empty($managedTeams)) {
                $selfTeam = $this->resolveUserTeam($localUser);
                if ($selfTeam) {
                    $managedTeams[] = $selfTeam;
                }
            }
        }

        return $managedTeams;
    }

    /**
     * Checks if a user is a Team Manager.
     */
    public function isTeamManager(User|int|string|null $user): bool
    {
        if (!$user) {
            return false;
        }

        $localUser = $user instanceof User ? $user : User::find($user);
        if ($localUser && in_array($localUser->role, ['admin', 'manager', 'line_manager'])) {
            return true;
        }

        $teams = $this->getManagedTeamsForUser($user);
        return count($teams) > 0;
    }

    /**
     * Retrieves all team members for a manager (both local User records and SF_User__c profiles).
     */
    public function getTeamMembersForManager(User $user): Collection
    {
        if ($user->role === 'admin') {
            return User::whereIn('role', ['user', 'line_manager'])->select('id', 'username', 'emp_id', 'email', 'name', 'role')->get();
        }

        $teams = $this->getManagedTeamsForUser($user);

        // Find all SF Users in those teams
        $sfEmails = SalesforceSfUser::whereIn('team', $teams)->whereNotNull('emp_email')->pluck('emp_email')->toArray();
        $sfCodes = SalesforceSfUser::whereIn('team', $teams)->whereNotNull('emp_code')->pluck('emp_code')->toArray();
        $sfNames = SalesforceSfUser::whereIn('team', $teams)->whereNotNull('name')->pluck('name')->toArray();

        // Direct and recursive subordinates
        $directSubordinateIds = User::where('manager_id', $user->id)->pluck('id')->toArray();
        $allSubordinateIds = $directSubordinateIds;
        if ($user->role === 'manager' && !empty($directSubordinateIds)) {
            $subSubIds = User::whereIn('manager_id', $directSubordinateIds)->pluck('id')->toArray();
            $allSubordinateIds = array_unique(array_merge($allSubordinateIds, $subSubIds));
        }

        $members = User::where(function ($q) use ($user, $teams, $sfEmails, $sfCodes, $sfNames, $allSubordinateIds) {
            $q->whereIn('id', $allSubordinateIds)
              ->orWhereIn('email', $sfEmails)
              ->orWhereIn('emp_id', $sfCodes)
              ->orWhereIn('username', $sfNames);
        })
        ->where('id', '!=', $user->id)
        ->select('id', 'username', 'emp_id', 'email', 'name', 'role')
        ->distinct()
        ->get();

        return $members;
    }

    /**
     * Checks if a manager has authority over a specific user.
     */
    public function isManagerOfUser(User $manager, User $member): bool
    {
        if ($manager->id === $member->id) {
            return true;
        }

        if ($manager->role === 'admin') {
            return true;
        }

        if ($member->manager_id === $manager->id) {
            return true;
        }

        $managedTeams = $this->getManagedTeamsForUser($manager);
        $memberTeam = $this->resolveUserTeam($member);

        return $memberTeam && in_array($memberTeam, $managedTeams);
    }

    /**
     * Backfills a Campaign model with its team, manager_salesforce_id, and manager_user_id.
     */
    public function backfillCampaign(Campaign $campaign): void
    {
        if (!$campaign->user_id) {
            return;
        }

        $user = $campaign->user ?: User::find($campaign->user_id);
        if (!$user) {
            return;
        }

        $team = $campaign->team ?: $this->resolveUserTeam($user);
        $manager = $this->resolveTeamManager($team);

        $campaign->update([
            'team' => $team,
            'manager_salesforce_id' => $manager ? $manager['salesforce_id'] : $campaign->manager_salesforce_id,
            'manager_user_id' => $manager && $manager['local_user_id'] ? $manager['local_user_id'] : ($campaign->manager_user_id ?: $user->manager_id),
        ]);
    }
}
