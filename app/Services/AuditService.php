<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class AuditService
{
    /**
     * Log user activity or security event.
     */
    public function logActivity(?int $userId, string $action, ?string $details = null, ?string $ipAddress = null): void
    {
        try {
            AuditLog::create([
                'user_id' => $userId,
                'action' => $action,
                'details' => $details,
                'ip_address' => $ipAddress,
            ]);
        } catch (\Throwable $e) {
            Log::error('[AuditLog Error] Failed to log activity: ' . $e->getMessage());
        }
    }
}
