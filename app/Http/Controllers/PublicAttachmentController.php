<?php

namespace App\Http\Controllers;

use App\Models\EmailAttachment;
use App\Services\AttachmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PublicAttachmentController extends Controller
{
    public function __construct(
        protected AttachmentService $attachmentService
    ) {}

    /**
     * Publicly downloadable attachment endpoint.
     * Accessible by email recipients without login or session authentication.
     */
    public function download(Request $request, string $token): Response
    {
        // 1. Sanitize and validate token parameter format
        if (empty($token) || !preg_match('/^[a-zA-Z0-9_\.-]{16,128}$/', $token)) {
            return $this->respondNotFound($request, 'Invalid or malformed attachment token.');
        }

        // 2. Find attachment by hash or token
        $attachment = $this->attachmentService->findByToken($token);
        if (!$attachment) {
            return $this->respondNotFound($request, 'The requested attachment link does not exist or has been removed.');
        }

        // 3. Check if manually revoked
        if ($attachment->isRevoked()) {
            return $this->respondAccessDenied(
                $request,
                'This attachment download link has been revoked by the sender.',
                403
            );
        }

        // 4. Check if expired
        if ($attachment->isExpired()) {
            return $this->respondAccessDenied(
                $request,
                'This attachment download link has expired and is no longer available.',
                410
            );
        }

        // 5. Prevent directory traversal attacks
        if (str_contains($attachment->storage_path, '..') || str_contains($attachment->storage_path, "\0")) {
            Log::warning("[PublicAttachmentController] Directory traversal attempt detected on token: {$token}");
            return $this->respondNotFound($request, 'File cannot be accessed.');
        }

        // 6. Verify physical file exists in storage
        $diskName = $attachment->disk ?: 'local';
        $disk = Storage::disk($diskName);

        if (!$disk->exists($attachment->storage_path)) {
            // Check fallback legacy locations if applicable
            $fallbackPath = "attachments/{$attachment->campaign_id}/{$attachment->original_filename}";
            if ($disk->exists($fallbackPath)) {
                $attachment->update(['storage_path' => $fallbackPath]);
            } else {
                Log::warning("[PublicAttachmentController] Physical file missing for attachment ID {$attachment->id} at path {$attachment->storage_path}");
                return $this->respondNotFound($request, 'The requested file is no longer available on the server.');
            }
        }

        // 7. Track download analytics
        $attachment->recordDownload();

        // 8. Prepare response headers
        $safeOriginalFilename = $attachment->original_filename;
        $mimeType = $attachment->mime_type ?: $this->attachmentService->guessMimeType($safeOriginalFilename);
        $fileSize = $attachment->file_size ?: $disk->size($attachment->storage_path);

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $fileSize,
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $disk->download(
            $attachment->storage_path,
            $safeOriginalFilename,
            $headers
        );
    }

    /**
     * Helper to render clean 404 response.
     */
    protected function respondNotFound(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => $message, 'status' => 404], 404);
        }

        return response()->view('attachments.not_found', [
            'message' => $message,
        ], 404);
    }

    /**
     * Helper to render clean 403 / 410 Access Denied response.
     */
    protected function respondAccessDenied(Request $request, string $reason, int $statusCode = 403): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => 'Access denied', 'reason' => $reason, 'status' => $statusCode], $statusCode);
        }

        return response()->view('attachments.access_denied', [
            'reason' => $reason,
            'statusCode' => $statusCode,
        ], $statusCode);
    }
}
