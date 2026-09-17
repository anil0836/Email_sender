<?php

namespace App\Http\Controllers;

use App\Models\InboundReply;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RepliesController extends Controller
{
    /**
     * Show Inbound Replies view.
     */
    public function index()
    {
        return view('replies.index');
    }

    /**
     * Inbound replies feed JSON API.
     */
    public function apiReplies(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        $role = $user ? $user->role : 'user';
        $username = $user ? $user->username : 'user';

        $query = DB::table('inbound_replies as r')
            ->join('campaigns as c', 'r.campaign_id', '=', 'c.id')
            ->select('r.*', 'c.subject as original_subject')
            ->orderBy('r.received_at', 'desc');

        if ($role !== 'admin') {
            $query->where('r.mapped_owner_id', $username);
        }

        return response()->json($query->get());
    }
}
