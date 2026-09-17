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
        if (!in_array($user->role, ['admin', 'manager'])) {
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
        $userRole = $user->role;
        $userId = $user->id;

        if (!in_array($userRole, ['admin', 'manager'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $teamMemberId = $request->input('team_member_id');

        $query = DB::table('campaigns')
            ->join('users', 'campaigns.user_id', '=', 'users.id')
            ->leftJoin('users as approvers', 'campaigns.approved_by', '=', 'approvers.id')
            ->select(
                'campaigns.id',
                'campaigns.subject',
                'campaigns.status',
                'campaigns.created_at',
                'campaigns.scheduled_at',
                'campaigns.total_requested',
                'campaigns.total_approved',
                'campaigns.total_blocked',
                'campaigns.attachments',
                'users.username',
                'users.emp_id',
                'users.email as user_email',
                'campaigns.approval_remark',
                'campaigns.approval_at',
                'approvers.username as approver_username'
            );

        if ($userRole === 'admin') {
            if ($teamMemberId) {
                $query->where('campaigns.user_id', $teamMemberId);
            }
        } else {
            $query->where('users.manager_id', $userId);
            if ($teamMemberId) {
                $query->where('campaigns.user_id', $teamMemberId);
            }
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
        $userRole = $user->role;
        $userId = $user->id;

        if (!in_array($userRole, ['admin', 'manager'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($userRole === 'admin') {
            $members = User::where('role', 'user')->select('id', 'username', 'emp_id', 'email', 'role')->get();
        } else {
            $members = User::where('manager_id', $userId)->where('role', 'user')->select('id', 'username', 'emp_id', 'email', 'role')->get();
        }

        return response()->json($members);
    }
}
