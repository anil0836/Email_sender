<?php

namespace App\Http\Controllers;

use App\Models\CampaignTemplate;
use App\Models\User;
use App\Models\UserSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TemplateSignatureController extends Controller
{
    /**
     * Signatures List and Create API.
     */
    public function apiSignatures(Request $request)
    {
        $userId = session('user_id') ?: (Auth::check() ? Auth::id() : null);
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($request->isMethod('get')) {
            $signatures = UserSignature::where('user_id', $userId)->get();
            return response()->json($signatures);
        }

        if ($request->isMethod('post')) {
            $name = $request->input('name');
            $content = $request->input('content');

            if (!$name || !$content) {
                return response()->json(['error' => 'Name and Content are required'], 400);
            }

            $sig = UserSignature::create([
                'user_id' => $userId,
                'name' => $name,
                'content' => $content,
            ]);

            return response()->json([
                'success' => true,
                'signature_id' => $sig->id,
                'message' => 'Signature saved successfully!',
            ]);
        }

        return response()->json(['error' => 'Method not allowed'], 405);
    }

    /**
     * Delete Signature API.
     */
    public function apiDeleteSignature(int $id)
    {
        $userId = session('user_id') ?: (Auth::check() ? Auth::id() : null);
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        UserSignature::where('id', $id)->where('user_id', $userId)->delete();

        return response()->json(['success' => true, 'message' => 'Signature deleted.']);
    }

    /**
     * Templates List and Create API.
     */
    public function apiTemplates(Request $request)
    {
        $userId = session('user_id') ?: (Auth::check() ? Auth::id() : null);
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($request->isMethod('get')) {
            $templates = CampaignTemplate::where('user_id', $userId)->get();
            return response()->json($templates);
        }

        if ($request->isMethod('post')) {
            $name = $request->input('name');
            $subject = $request->input('subject');
            $body = $request->input('body');

            if (!$name || !$subject || !$body) {
                return response()->json(['error' => 'Name, Subject, and Body are required'], 400);
            }

            $tmpl = CampaignTemplate::create([
                'user_id' => $userId,
                'name' => $name,
                'subject' => $subject,
                'body' => $body,
            ]);

            return response()->json([
                'success' => true,
                'template_id' => $tmpl->id,
                'message' => 'Template saved successfully!',
            ]);
        }

        return response()->json(['error' => 'Method not allowed'], 405);
    }

    /**
     * Delete Template API.
     */
    public function apiDeleteTemplate(int $id)
    {
        $userId = session('user_id') ?: (Auth::check() ? Auth::id() : null);
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        CampaignTemplate::where('id', $id)->where('user_id', $userId)->delete();

        return response()->json(['success' => true, 'message' => 'Template deleted.']);
    }

    /**
     * Current user assigned settings API.
     */
    public function apiUserAssignedSettings()
    {
        $userId = session('user_id') ?: (Auth::check() ? Auth::id() : null);
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $row = DB::table('users as u')
            ->leftJoin('servers as s', 'u.assigned_server_id', '=', 's.id')
            ->leftJoin('sending_domains as d', 'u.assigned_domain_id', '=', 'd.id')
            ->where('u.id', $userId)
            ->select('u.assigned_server_id', 'u.assigned_domain_id', 's.name as server_name', 'd.domain_name')
            ->first();

        return response()->json($row ?: (object) []);
    }
}
