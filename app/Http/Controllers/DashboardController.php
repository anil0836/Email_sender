<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\RecipientClick;
use App\Models\RecipientLog;
use App\Models\RecipientOpen;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Show dashboard view.
     */
    public function index()
    {
        return view('dashboard');
    }

    /**
     * Aggregated Dashboard Statistics API.
     */
    public function apiStats(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $userId = $user->id;
        $role = $user->role;

        $teamService = app(\App\Services\TeamService::class);
        $managedTeams = $teamService->getManagedTeamsForUser($user);
        $isMgr = $role === 'admin' || $role === 'manager' || count($managedTeams) > 0;

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $campaignId = $request->input('campaign_id');
        $teamMemberId = $request->input('team_member_id') ?: $request->input('user_id');

        // Apply filters helper
        $applyFilters = function ($query) use ($role, $userId, $startDate, $endDate, $campaignId, $teamMemberId, $managedTeams, $isMgr) {
            if ($startDate) {
                $query->where('campaigns.created_at', '>=', $startDate . ' 00:00:00');
            }
            if ($endDate) {
                $query->where('campaigns.created_at', '<=', $endDate . ' 23:59:59');
            }
            if ($campaignId) {
                $query->where('campaigns.id', $campaignId);
            }

            if ($role === 'admin') {
                if ($teamMemberId) {
                    $query->where('campaigns.user_id', (int) $teamMemberId);
                }
            } elseif ($isMgr) {
                $subordinateIds = User::where('manager_id', $userId)->pluck('id')->toArray();
                $subordinateIds[] = $userId;

                if ($teamMemberId) {
                    $query->where('campaigns.user_id', (int) $teamMemberId);
                } else {
                    $query->where(function ($q) use ($userId, $subordinateIds, $managedTeams) {
                        $q->where('campaigns.user_id', $userId)
                          ->orWhere('campaigns.manager_user_id', $userId);

                        if (count($managedTeams) > 0) {
                            $q->orWhereIn('campaigns.team', $managedTeams);
                        }
                        if (count($subordinateIds) > 0) {
                            $q->orWhereIn('campaigns.user_id', $subordinateIds);
                        }
                    });
                }
            } else {
                $query->where('campaigns.user_id', $userId);
            }
        };

        // Team members lookup
        $teamMembers = [];
        if ($role === 'admin') {
            $teamMembers = User::select('id', 'username', 'email', 'role')->orderBy('username')->get()->toArray();
        } elseif ($isMgr) {
            $membersColl = $teamService->getTeamMembersForManager($user);
            $teamMembers = $membersColl->toArray();
            $hasSelf = false;
            foreach ($teamMembers as $tm) {
                if ($tm['id'] == $userId) {
                    $hasSelf = true;
                    break;
                }
            }
            if (!$hasSelf) {
                array_unshift($teamMembers, [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                ]);
            }
        }

        $sentStatuses = ['sent', 'delivered', 'opened', 'unsubscribed', 'bounce', 'spam_complaint'];

        // 1. Core counters
        $baseLogQuery = DB::table('recipient_logs')
            ->join('campaigns', 'recipient_logs.campaign_id', '=', 'campaigns.id');
        $applyFilters($baseLogQuery);

        $countsRow = (clone $baseLogQuery)->selectRaw("
            COUNT(CASE WHEN recipient_logs.delivery_status IN ('" . implode("','", $sentStatuses) . "') THEN 1 END) as total_sent,
            COUNT(CASE WHEN recipient_logs.delivery_status IN ('delivered', 'opened', 'unsubscribed') THEN 1 END) as delivered,
            COUNT(CASE WHEN recipient_logs.delivery_status = 'spam_complaint' THEN 1 END) as spam_marked,
            COUNT(CASE WHEN recipient_logs.delivery_status = 'bounce' THEN 1 END) as bounced,
            COUNT(CASE WHEN recipient_logs.delivery_status = 'failed' THEN 1 END) as undelivered,
            COUNT(CASE WHEN recipient_logs.delivery_status = 'opened' THEN 1 END) as opened,
            COUNT(CASE WHEN recipient_logs.delivery_status = 'unsubscribed' THEN 1 END) as unsubscribed
        ")->first();

        $totalSent = (int) ($countsRow->total_sent ?? 0);
        $delivered = (int) ($countsRow->delivered ?? 0);
        $spamMarked = (int) ($countsRow->spam_marked ?? 0);
        $bounced = (int) ($countsRow->bounced ?? 0);
        $undelivered = (int) ($countsRow->undelivered ?? 0);
        $opened = (int) ($countsRow->opened ?? 0);
        $unsubscribed = (int) ($countsRow->unsubscribed ?? 0);

        // Unique Opens
        $opensQuery = DB::table('recipient_logs')
            ->join('campaigns', 'recipient_logs.campaign_id', '=', 'campaigns.id')
            ->join('recipient_opens', 'recipient_opens.recipient_log_id', '=', 'recipient_logs.id');
        $applyFilters($opensQuery);
        $uniqueOpens = $opensQuery->distinct()->count('recipient_logs.id');

        // Unique Clicks
        $clicksQuery = DB::table('recipient_logs')
            ->join('campaigns', 'recipient_logs.campaign_id', '=', 'campaigns.id')
            ->join('recipient_clicks', 'recipient_clicks.recipient_log_id', '=', 'recipient_logs.id');
        $applyFilters($clicksQuery);
        $uniqueClicks = $clicksQuery->distinct()->count('recipient_logs.id');

        $openRate = $totalSent > 0 ? round(($uniqueOpens / $totalSent) * 100, 2) : 0.0;
        $clickRate = $totalSent > 0 ? round(($uniqueClicks / $totalSent) * 100, 2) : 0.0;
        $unsubRate = $totalSent > 0 ? round(($unsubscribed / $totalSent) * 100, 2) : 0.0;

        $counters = [
            'total_sent' => $totalSent,
            'delivered' => $delivered,
            'spam_marked' => $spamMarked,
            'bounced' => $bounced,
            'undelivered' => $undelivered,
            'opened' => $opened,
            'unsubscribed' => $unsubscribed,
            'unique_opens' => $uniqueOpens,
            'unique_clicks' => $uniqueClicks,
            'open_rate' => $openRate,
            'click_rate' => $clickRate,
            'unsubscribe_rate' => $unsubRate,
        ];

        // 2. Geolocation Open Metrics
        $geoQuery = DB::table('recipient_opens')
            ->join('recipient_logs', 'recipient_opens.recipient_log_id', '=', 'recipient_logs.id')
            ->join('campaigns', 'recipient_logs.campaign_id', '=', 'campaigns.id');
        $applyFilters($geoQuery);
        $geoData = $geoQuery
            ->select('recipient_opens.country', 'recipient_opens.region', 'recipient_opens.city', DB::raw('COUNT(*) as open_count'))
            ->groupBy('recipient_opens.country', 'recipient_opens.region', 'recipient_opens.city')
            ->orderByDesc('open_count')
            ->get()
            ->toArray();

        // 3. Volumetric Trend Chart (Daily sending volumes)
        $chartQuery = DB::table('recipient_logs')
            ->join('campaigns', 'recipient_logs.campaign_id', '=', 'campaigns.id')
            ->whereIn('recipient_logs.delivery_status', $sentStatuses)
            ->whereNotNull('recipient_logs.sent_at');
        $applyFilters($chartQuery);

        $chartData = $chartQuery
            ->selectRaw("substr(recipient_logs.sent_at, 1, 10) as send_date, COUNT(*) as count")
            ->groupBy('send_date')
            ->orderBy('send_date', 'asc')
            ->get()
            ->toArray();

        // 4. Open Timeline Chart Data (hourly engagement)
        $timelineQuery = DB::table('recipient_opens')
            ->join('recipient_logs', 'recipient_opens.recipient_log_id', '=', 'recipient_logs.id')
            ->join('campaigns', 'recipient_logs.campaign_id', '=', 'campaigns.id');
        $applyFilters($timelineQuery);

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $openTimeExpr = $isSqlite 
            ? "substr(recipient_opens.opened_at, 1, 13) || ':00'" 
            : "CONCAT(SUBSTR(recipient_opens.opened_at, 1, 13), ':00')";

        $timelineData = $timelineQuery
            ->selectRaw("{$openTimeExpr} as open_time, COUNT(*) as count")
            ->groupBy('open_time')
            ->orderBy('open_time', 'asc')
            ->get()
            ->toArray();

        // 5. Recent Campaigns
        $recentCampQuery = DB::table('campaigns')
            ->join('users', 'campaigns.user_id', '=', 'users.id');
        $applyFilters($recentCampQuery);

        $recentCampaigns = $recentCampQuery
            ->select('campaigns.*', 'users.username')
            ->orderBy('campaigns.created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();

        // 6. Link Clicks Breakdown
        $linksQuery = DB::table('recipient_clicks')
            ->join('recipient_logs', 'recipient_clicks.recipient_log_id', '=', 'recipient_logs.id')
            ->join('campaigns', 'recipient_logs.campaign_id', '=', 'campaigns.id');
        $applyFilters($linksQuery);

        $linkClicks = $linksQuery
            ->select('recipient_clicks.url', DB::raw('COUNT(*) as click_count'), DB::raw('COUNT(DISTINCT recipient_clicks.recipient_log_id) as unique_clicks'))
            ->groupBy('recipient_clicks.url')
            ->orderByDesc('click_count')
            ->limit(10)
            ->get()
            ->toArray();

        // 7. Device Type Breakdown
        $deviceQuery = DB::table('recipient_opens')
            ->join('recipient_logs', 'recipient_opens.recipient_log_id', '=', 'recipient_logs.id')
            ->join('campaigns', 'recipient_logs.campaign_id', '=', 'campaigns.id');
        $applyFilters($deviceQuery);

        $deviceBreakdown = $deviceQuery
            ->selectRaw("
                CASE 
                    WHEN recipient_opens.user_agent LIKE '%Mobi%' OR recipient_opens.user_agent LIKE '%Android%' OR recipient_opens.user_agent LIKE '%iPhone%' THEN 'Mobile'
                    WHEN recipient_opens.user_agent LIKE '%Tablet%' OR recipient_opens.user_agent LIKE '%iPad%' THEN 'Tablet'
                    ELSE 'Desktop'
                END as device_type,
                COUNT(*) as count
            ")
            ->groupBy('device_type')
            ->get()
            ->toArray();

        return response()->json([
            'counters' => $counters,
            'geo_data' => $geoData,
            'chart_data' => $chartData,
            'timeline_data' => $timelineData,
            'recent_campaigns' => $recentCampaigns,
            'link_clicks' => $linkClicks,
            'device_breakdown' => $deviceBreakdown,
            'team_members' => $teamMembers,
            'user_role' => $role,
        ]);
    }

    /**
     * Dashboard Recipient List drilldown modal API.
     */
    public function apiRecipientList(Request $request)
    {
        $user = Auth::user() ?: User::find(session('user_id'));
        $userId = $user->id;
        $role = $user->role;

        $statusType = $request->input('status', 'total');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $campaignId = $request->input('campaign_id');
        $teamMemberId = $request->input('team_member_id') ?: $request->input('user_id');

        $query = DB::table('recipient_logs')
            ->join('campaigns', 'recipient_logs.campaign_id', '=', 'campaigns.id');

        // Status condition
        if ($statusType === 'total') {
            $query->whereIn('recipient_logs.delivery_status', ['sent', 'delivered', 'opened', 'unsubscribed', 'bounce', 'spam_complaint']);
        } elseif ($statusType === 'delivered') {
            $query->whereIn('recipient_logs.delivery_status', ['delivered', 'opened', 'unsubscribed']);
        } elseif ($statusType === 'bounce') {
            $query->where('recipient_logs.delivery_status', 'bounce');
        } elseif ($statusType === 'failed') {
            $query->where('recipient_logs.delivery_status', 'failed');
        } elseif ($statusType === 'spam') {
            $query->where('recipient_logs.delivery_status', 'spam_complaint');
        } elseif ($statusType === 'unsubscribed') {
            $query->where('recipient_logs.delivery_status', 'unsubscribed');
        }

        // Date and campaign filters
        if ($startDate) {
            $query->where('campaigns.created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $query->where('campaigns.created_at', '<=', $endDate . ' 23:59:59');
        }
        if ($campaignId) {
            $query->where('campaigns.id', $campaignId);
        }

        // Role filter
        $teamService = app(\App\Services\TeamService::class);
        $managedTeams = $teamService->getManagedTeamsForUser($user);
        $isMgr = $role === 'admin' || $role === 'manager' || count($managedTeams) > 0;

        if ($role === 'admin') {
            if ($teamMemberId) {
                $query->where('campaigns.user_id', (int) $teamMemberId);
            }
        } elseif ($isMgr) {
            $subordinateIds = User::where('manager_id', $userId)->pluck('id')->toArray();
            $subordinateIds[] = $userId;

            if ($teamMemberId) {
                $query->where('campaigns.user_id', (int) $teamMemberId);
            } else {
                $query->where(function ($q) use ($userId, $subordinateIds, $managedTeams) {
                    $q->where('campaigns.user_id', $userId)
                      ->orWhere('campaigns.manager_user_id', $userId);

                    if (count($managedTeams) > 0) {
                        $q->orWhereIn('campaigns.team', $managedTeams);
                    }
                    if (count($subordinateIds) > 0) {
                        $q->orWhereIn('campaigns.user_id', $subordinateIds);
                    }
                });
            }
        } else {
            $query->where('campaigns.user_id', $userId);
        }

        $recipients = $query
            ->select('recipient_logs.email', 'recipient_logs.salesforce_record_id', 'recipient_logs.sent_at', 'campaigns.subject as campaign_subject')
            ->orderBy('recipient_logs.sent_at', 'desc')
            ->get();

        return response()->json($recipients);
    }
}
