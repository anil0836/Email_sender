<?php

namespace App\Http\Controllers;

use App\Models\CampaignTemplate;
use App\Models\User;
use App\Models\UserSignature;
use App\Services\CampaignMergeFieldService;
use App\Services\SalesforceService;
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
            $signatures = UserSignature::where('user_id', $userId)
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($sig) {
                    $item = $sig->toArray();
                    $item['rendered_html'] = $sig->renderHtml();
                    return $item;
                });
            return response()->json($signatures);
        }

        if ($request->isMethod('post')) {
            $name = $request->input('name');
            if (!$name) {
                return response()->json(['error' => 'Name is required'], 400);
            }

            $isDefault = $request->boolean('is_default');
            $existingCount = UserSignature::where('user_id', $userId)->count();
            if ($isDefault || $existingCount === 0) {
                $isDefault = true;
                UserSignature::where('user_id', $userId)->update(['is_default' => false]);
            }

            $sig = UserSignature::create([
                'user_id' => $userId,
                'name' => $name,
                'sender_name' => $request->input('sender_name'),
                'job_title' => $request->input('job_title'),
                'company_name' => $request->input('company_name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'website' => $request->input('website'),
                'address' => $request->input('address'),
                'content' => $request->input('content') ?? '',
                'is_default' => $isDefault,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'signature_id' => $sig->id,
                'signature' => $sig,
                'rendered_html' => $sig->renderHtml(),
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
            $templates = CampaignTemplate::where('user_id', $userId)
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
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
                'is_default' => $request->boolean('is_default'),
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'template_id' => $tmpl->id,
                'template' => $tmpl,
                'message' => 'Template saved successfully!',
            ]);
        }

        return response()->json(['error' => 'Method not allowed'], 405);
    }

    /**
     * Get specific template.
     */
    public function apiGetTemplate(int $id)
    {
        $userId = session('user_id') ?: (Auth::check() ? Auth::id() : null);
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $template = CampaignTemplate::where('id', $id)->where('user_id', $userId)->firstOrFail();

        return response()->json([
            'success' => true,
            'template' => $template,
        ]);
    }

    /**
     * Update specific template.
     */
    public function apiUpdateTemplate(Request $request, int $id)
    {
        $userId = session('user_id') ?: (Auth::check() ? Auth::id() : null);
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $template = CampaignTemplate::where('id', $id)->where('user_id', $userId)->firstOrFail();

        $name = $request->input('name', $template->name);
        $subject = $request->input('subject', $template->subject);
        $body = $request->input('body', $template->body);

        $template->update([
            'name' => $name,
            'subject' => $subject,
            'body' => $body,
        ]);

        return response()->json([
            'success' => true,
            'template' => $template,
            'message' => 'Template updated successfully!',
        ]);
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
     * Return available merge field tokens.
     */
    public function apiMergeFields(CampaignMergeFieldService $mergeFieldService)
    {
        return response()->json([
            'success' => true,
            'fields' => $mergeFieldService->getAvailableMergeFields(),
        ]);
    }

    /**
     * Live Preview API for email subject and body with resolved recipient merge fields.
     */
    public function apiPreview(Request $request, CampaignMergeFieldService $mergeFieldService, SalesforceService $sfService)
    {
        $userId = session('user_id') ?: (Auth::check() ? Auth::id() : null);
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $user = Auth::user() ?: User::find($userId);

        $subject = (string)$request->input('subject', '');
        $body = (string)$request->input('body', '');
        $signatureId = $request->input('signature_id');
        $recipientId = $request->input('recipient_id');

        $signatureHtml = null;
        if ($signatureId) {
            $sig = UserSignature::where('id', $signatureId)->where('user_id', $userId)->first();
            if ($sig) {
                $signatureHtml = $sig->renderHtml();
            }
        }

        $sfRecord = null;
        if ($recipientId) {
            $sfRecord = $sfService->getSalesforceRecord($recipientId);
            if (!$sfRecord) {
                $sfRecord = $sfService->getSalesforceRecordByEmail($recipientId);
            }
        }

        if (!$sfRecord) {
            $sfRecord = [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'name' => 'John Doe',
                'company' => 'Acme Technologies Inc.',
                'email' => 'john.doe@example.com',
                'phone' => '+1 (555) 234-5678',
                'owner_name' => $user ? ($user->name ?: $user->username) : 'Salesforce Owner',
            ];
        }

        $resolvedSubject = $mergeFieldService->resolve($subject, $sfRecord, $user, null);
        $resolvedBody = $mergeFieldService->resolve($body, $sfRecord, $user, $signatureHtml, true);

        return response()->json([
            'success' => true,
            'resolved_subject' => $resolvedSubject,
            'resolved_body' => $resolvedBody,
            'recipient' => $mergeFieldService->extractRecipientData($sfRecord),
        ]);
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
