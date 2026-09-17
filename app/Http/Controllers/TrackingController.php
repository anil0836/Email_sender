<?php

namespace App\Http\Controllers;

use App\Models\GlobalSuppression;
use App\Models\RecipientClick;
use App\Models\RecipientLog;
use App\Models\RecipientOpen;
use App\Services\SalesforceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function __construct(
        protected SalesforceService $sfService
    ) {}

    /**
     * 1x1 Transparent Open Tracking Pixel.
     */
    public function trackOpen(string $token, Request $request)
    {
        $ipAddr = $request->ip();
        $userAgent = $request->userAgent();

        $simCountry = $request->input('country');
        $simRegion = $request->input('region');
        $simCity = $request->input('city');

        $log = RecipientLog::where('tracking_token', $token)->first();

        if ($log) {
            if (in_array($log->delivery_status, ['sent', 'delivered'])) {
                $log->update(['delivery_status' => 'opened']);
            }

            $country = $simCountry ?: 'US';
            $region = $simRegion ?: 'New York';
            $city = $simCity ?: 'New York';

            if (!$simCountry && in_array($ipAddr, ['127.0.0.1', '::1'])) {
                $geoChoices = [
                    ['US', 'California', 'Los Angeles'],
                    ['GB', 'England', 'London'],
                    ['IN', 'Karnataka', 'Bengaluru'],
                    ['JP', 'Tokyo', 'Shinjuku'],
                    ['DE', 'Berlin', 'Berlin'],
                ];
                $choice = $geoChoices[$log->id % count($geoChoices)];
                $country = $choice[0];
                $region = $choice[1];
                $city = $choice[2];
            }

            RecipientOpen::create([
                'recipient_log_id' => $log->id,
                'opened_at' => Carbon::now(),
                'ip_address' => $ipAddr,
                'user_agent' => $userAgent,
                'country' => $country,
                'region' => $region,
                'city' => $city,
            ]);
        }

        // 1x1 blank transparent GIF pixel binary data
        $gifData = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gifData, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Tracked Link Click Redirection.
     */
    public function trackClick(string $token, Request $request)
    {
        $url = $request->input('url');
        if (!$url) {
            return response('Missing destination URL', 400);
        }

        $ipAddr = $request->ip();
        $userAgent = $request->userAgent();

        $log = RecipientLog::where('tracking_token', $token)->first();

        if ($log) {
            RecipientClick::create([
                'recipient_log_id' => $log->id,
                'clicked_at' => Carbon::now(),
                'url' => $url,
                'ip_address' => $ipAddr,
                'user_agent' => $userAgent,
            ]);

            if (in_array($log->delivery_status, ['sent', 'delivered'])) {
                $log->update(['delivery_status' => 'opened']);
            }
        }

        return redirect()->away($url);
    }

    /**
     * Unsubscribe Handling & Salesforce Status Sync.
     */
    public function trackUnsubscribe(string $token, Request $request)
    {
        $log = RecipientLog::where('tracking_token', $token)->first();

        if (!$log) {
            return response('Invalid link.', 400);
        }

        $email = $log->email;
        $sfId = $log->salesforce_record_id;

        if ($request->isMethod('post') || $request->input('confirm') === '1') {
            // Add to global suppression list
            GlobalSuppression::firstOrCreate(
                ['email' => $email],
                ['reason' => 'Unsubscribe Link Click', 'added_at' => Carbon::now()]
            );

            // Update delivery_status
            $log->update(['delivery_status' => 'unsubscribed']);

            // Synchronize status back to Salesforce Mock
            $this->sfService->updateSalesforceRecord($sfId, ['opted_out' => true]);

            return view('tracking.unsubscribed', ['email' => $email, 'success' => true]);
        }

        return view('tracking.unsubscribed', ['email' => $email, 'success' => false, 'token' => $token]);
    }
}
