<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ManagerController extends Controller
{
    /**
     * Show manager team campaigns view.
     */
    public function campaignsView()
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        if (!$user) {
            return redirect()->route('login');
        }

        $teamService = app(\App\Services\TeamService::class);
        if (!in_array($user->role, ['admin', 'manager', 'line_manager']) && !$teamService->isTeamManager($user)) {
            return redirect()->route('dashboard_view')->with('danger', 'Unauthorized access.');
        }

        return view('campaigns.manager');
    }

    /**
     * Team campaigns list JSON API.
     */
    public function apiCampaigns(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $teamService = app(\App\Services\TeamService::class);
        $userRole = $user->role;

        if (!in_array($userRole, ['admin', 'manager', 'line_manager']) && !$teamService->isTeamManager($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $teamMemberId = $request->input('team_member_id');
        $teamFilter = $request->input('team');

        $query = Campaign::accessibleBy($user)
            ->join('users', 'campaigns.user_id', '=', 'users.id')
            ->leftJoin('users as approvers', 'campaigns.approved_by', '=', 'approvers.id')
            ->leftJoin('users as cur_app', 'campaigns.current_approver_id', '=', 'cur_app.id')
            ->leftJoin('users as line_mgr', 'campaigns.line_manager_id', '=', 'line_mgr.id')
            ->leftJoin('users as rejecters', 'campaigns.rejected_by', '=', 'rejecters.id')
            ->leftJoin('salesforce_users as sf_mgr', 'campaigns.manager_salesforce_id', '=', 'sf_mgr.salesforce_id')
            ->select(
                'campaigns.id',
                'campaigns.subject',
                'campaigns.status',
                'campaigns.team',
                'campaigns.user_id',
                'campaigns.manager_salesforce_id',
                'campaigns.manager_user_id',
                'campaigns.line_manager_id',
                'campaigns.current_approver_id',
                'campaigns.rejected_by',
                'campaigns.rejection_reason',
                'campaigns.rejected_at',
                'campaigns.created_at',
                'campaigns.scheduled_at',
                'campaigns.total_requested',
                'campaigns.total_approved',
                'campaigns.total_blocked',
                'campaigns.attachments',
                'users.username',
                'users.name as sender_name',
                'users.emp_id',
                'users.email as user_email',
                'campaigns.approval_remark',
                'campaigns.approval_at',
                'approvers.username as approver_username',
                'approvers.name as approver_name',
                'cur_app.username as current_approver_username',
                'cur_app.name as current_approver_name',
                'line_mgr.name as line_manager_name',
                'rejecters.username as rejecter_username',
                'rejecters.name as rejecter_name',
                'sf_mgr.name as manager_name'
            );

        if ($teamMemberId) {
            $query->where('campaigns.user_id', $teamMemberId);
        }

        if ($teamFilter) {
            $query->where('campaigns.team', $teamFilter);
        }

        $query->orderBy('campaigns.created_at', 'desc');

        return response()->json($query->get());
    }

    /**
     * Team members lookup JSON API.
     */
    public function apiTeamMembers(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $teamService = app(\App\Services\TeamService::class);
        $userRole = $user->role;

        if (!in_array($userRole, ['admin', 'manager', 'line_manager']) && !$teamService->isTeamManager($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $members = $teamService->getTeamMembersForManager($user);

        return response()->json($members);
    }
}
