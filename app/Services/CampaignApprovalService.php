<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignApproval;
use App\Models\RecipientLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CampaignApprovalService
{
    public function __construct(
        protected ?CampaignProcessingService $processingService = null
    ) {
        $this->processingService = $processingService ?: app(CampaignProcessingService::class);
    }

    /**
     * Determines initial campaign approval state based on the creator's role and manager_id hierarchy.
     * Enforces strict single-level approval hierarchy:
     * - User -> Line Manager (email approval of user must go only to its line manager)
     * - Line Manager -> Manager (only line manager flow goes to manager)
     * - Manager / Admin -> Direct sending (no approval required)
     *
     * @param User $creator
     * @return array{status: string, approval_stage?: string|null, current_approver_id: int|null, line_manager_id: int|null, manager_id: int|null, error: string|null}
     */
    public function determineInitialApprovalState(User $creator): array
    {
        $role = strtolower(trim((string)$creator->role));

        // 1. Admin: Direct sending, no approval required
        if ($role === 'admin') {
            return [
                'status' => 'approved',
                'approval_stage' => null,
                'current_approver_id' => null,
                'line_manager_id' => null,
                'manager_id' => null,
                'error' => null,
            ];
        }

        // 2. Manager: Top level of sending hierarchy, no further approval required
        if ($role === 'manager') {
            return [
                'status' => 'approved',
                'approval_stage' => null,
                'current_approver_id' => null,
                'line_manager_id' => null,
                'manager_id' => null,
                'error' => null,
            ];
        }

        // 3. Line Manager: Requires approval from their designated Manager
        // Only Line Manager flow goes to Manager!
        if (in_array($role, ['line_manager', 'purchase _manager', 'purchase_manager'])) {
            if (empty($creator->manager_id)) {
                return [
                    'status' => 'error',
                    'current_approver_id' => null,
                    'line_manager_id' => null,
                    'manager_id' => null,
                    'error' => 'Approval hierarchy is incomplete. No Manager is assigned to this Line Manager.',
                ];
            }

            $superior = User::find($creator->manager_id);
            if (!$superior) {
                return [
                    'status' => 'error',
                    'current_approver_id' => null,
                    'line_manager_id' => null,
                    'manager_id' => null,
                    'error' => 'Approval hierarchy is incomplete. Assigned superior does not exist.',
                ];
            }

            $superiorRole = strtolower(trim((string)$superior->role));
            if (!in_array($superiorRole, ['manager', 'admin'])) {
                return [
                    'status' => 'error',
                    'current_approver_id' => null,
                    'line_manager_id' => null,
                    'manager_id' => null,
                    'error' => 'Approval hierarchy is incomplete. Assigned superior is not a Manager.',
                ];
            }

            return [
                'status' => 'pending_manager',
                'approval_stage' => 'pending_manager',
                'current_approver_id' => $superior->id,
                'line_manager_id' => null,
                'manager_id' => $superior->id,
                'error' => null,
            ];
        }

        // 4. Normal User (role === 'user' or default):
        // Email approval of user MUST go ONLY to its line manager!
        $managerId = $creator->manager_id;
        if (empty($managerId)) {
            // Check if user has a team line manager via TeamService
            $teamService = app(\App\Services\TeamService::class);
            $team = $teamService->resolveUserTeam($creator);
            if ($team) {
                $mgrInfo = $teamService->resolveTeamManager($team);
                if (!empty($mgrInfo['local_user_id']) && (int)$mgrInfo['local_user_id'] !== (int)$creator->id) {
                    $potentialSuperior = User::find($mgrInfo['local_user_id']);
                    if ($potentialSuperior && ($potentialSuperior->role === 'line_manager' || $potentialSuperior->isTeamManager())) {
                        $managerId = $potentialSuperior->id;
                    }
                }
            }
        }

        if (empty($managerId)) {
            return [
                'status' => 'error',
                'current_approver_id' => null,
                'line_manager_id' => null,
                'manager_id' => null,
                'error' => 'Approval hierarchy is incomplete. User email approval must go only to its Line Manager. No Line Manager is assigned to this user.',
            ];
        }

        $superior = User::find($managerId);
        if (!$superior) {
            return [
                'status' => 'error',
                'current_approver_id' => null,
                'line_manager_id' => null,
                'manager_id' => null,
                'error' => 'Approval hierarchy is incomplete. Assigned Line Manager does not exist.',
            ];
        }

        $superiorRole = strtolower(trim((string)$superior->role));

        // Strict: User email approval goes ONLY to its line manager!
        // A Manager (role === 'manager') must NEVER receive user email approvals directly.
        if ($superiorRole === 'manager') {
            return [
                'status' => 'error',
                'current_approver_id' => null,
                'line_manager_id' => null,
                'manager_id' => null,
                'error' => 'Approval hierarchy is incomplete. User email approval must go only to its Line Manager, but assigned superior is not a Line Manager.',
            ];
        }

        $isSuperiorLineManager = in_array($superiorRole, ['line_manager', 'purchase _manager', 'purchase_manager', 'lead_manager', 'assistant_manager'])
            || str_contains($superiorRole, 'line_manager')
            || (str_contains($superiorRole, 'manager') && $superiorRole !== 'manager')
            || $superior->isTeamManager();

        if (!$isSuperiorLineManager) {
            return [
                'status' => 'error',
                'current_approver_id' => null,
                'line_manager_id' => null,
                'manager_id' => null,
                'error' => 'Approval hierarchy is incomplete. User email approval must go only to its Line Manager, but assigned superior is not a Line Manager.',
            ];
        }

        return [
            'status' => 'pending_line_manager',
            'approval_stage' => 'pending_line_manager',
            'current_approver_id' => $superior->id,
            'line_manager_id' => $superior->id,
            'manager_id' => null, // Manager does not approve user emails!
            'error' => null,
        ];
    }

    /**
     * Records the campaign submission in the audit history table.
     */
    public function recordSubmission(Campaign $campaign, User $creator): CampaignApproval
    {
        return CampaignApproval::create([
            'campaign_id' => $campaign->id,
            'approver_id' => $creator->id,
            'approver_role' => $creator->role,
            'action' => 'submitted',
            'previous_status' => 'draft',
            'new_status' => $campaign->status,
            'comment' => 'Campaign submitted for approval',
        ]);
    }

    /**
     * Checks if the given user has authority to approve this campaign at its current state.
     */
    public function canApprove(Campaign $campaign, User $user): bool
    {
        // Creator cannot approve their own campaign unless they are an Admin
        if ($campaign->user_id === $user->id && $user->role !== 'admin') {
            return false;
        }

        // Admin has global approval authority
        if ($user->role === 'admin') {
            return in_array($campaign->status, ['pending_line_manager', 'pending_manager', 'pending_approval']);
        }

        // User email approval awaiting Line Manager: ONLY the designated Line Manager can approve
        if ($campaign->status === 'pending_line_manager') {
            $isLineManager = in_array($user->role, ['line_manager', 'purchase _manager', 'purchase_manager', 'lead_manager', 'assistant_manager'])
                || str_contains((string)$user->role, 'line_manager')
                || $user->isTeamManager();

            if (!$isLineManager || $user->role === 'manager') {
                return false;
            }
            return (int)$user->id === (int)$campaign->current_approver_id
                || (int)$user->id === (int)$campaign->line_manager_id;
        }

        // Line Manager email approval awaiting Manager: ONLY the designated Manager can approve
        if ($campaign->status === 'pending_manager') {
            if ($user->role !== 'manager') {
                return false;
            }
            return (int)$user->id === (int)$campaign->current_approver_id
                || (int)$user->id === (int)$campaign->manager_user_id;
        }

        // Legacy pending_approval fallback
        if ($campaign->status === 'pending_approval') {
            if (!empty($campaign->current_approver_id)) {
                return (int)$user->id === (int)$campaign->current_approver_id;
            }
            $isLineManager = in_array($user->role, ['line_manager', 'purchase _manager', 'purchase_manager', 'lead_manager', 'assistant_manager'])
                || str_contains((string)$user->role, 'line_manager')
                || $user->isTeamManager();

            if ($isLineManager && ((int)$user->id === (int)$campaign->line_manager_id || (int)$user->id === (int)$campaign->manager_user_id)) {
                return true;
            }
            if ($user->role === 'manager' && (int)$user->id === (int)$campaign->manager_user_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the given user has authority to reject this campaign.
     */
    public function canReject(Campaign $campaign, User $user): bool
    {
        return $this->canApprove($campaign, $user);
    }

    /**
     * Executes approval of a campaign.
     * Completes single-level approval and transitions the campaign to queued/scheduled.
     *
     * @param Campaign $campaign
     * @param User $approver
     * @param string|null $remark
     * @return array{success: bool, message: string, new_status?: string, error?: string}
     */
    public function approve(Campaign $campaign, User $approver, ?string $remark = null): array
    {
        if (!$this->canApprove($campaign, $approver)) {
            return [
                'success' => false,
                'error' => 'Unauthorized: You do not have permission to approve this campaign at its current stage.',
            ];
        }

        $previousStatus = $campaign->status;
        $now = Carbon::now();
        $newStatus = $campaign->scheduled_at ? 'scheduled' : 'queued';
        $newDeliveryStatus = $newStatus;

        $campaign->update([
            'status' => $newStatus,
            'current_approver_id' => null,
            'approved_by' => $approver->id,
            'approval_remark' => $remark ?: 'Approved',
            'approval_at' => $now,
        ]);

        // Update Recipient Logs
        RecipientLog::where('campaign_id', $campaign->id)
            ->whereIn('delivery_status', ['pending_approval', 'pending_line_manager', 'pending_manager'])
            ->update(['delivery_status' => $newDeliveryStatus]);

        // Record in Campaign Approval History
        CampaignApproval::create([
            'campaign_id' => $campaign->id,
            'approver_id' => $approver->id,
            'approver_role' => $approver->role,
            'action' => 'approved',
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'comment' => $remark ?: 'Approved',
        ]);

        // Trigger Queue Processing if ready and not testing
        if ($newStatus === 'queued' && !app()->environment('testing')) {
            try {
                $this->processingService->processQueuedEmails();
            } catch (\Throwable $e) {
                Log::error("[CampaignApprovalService] Mail processing trigger error: " . $e->getMessage());
            }
        }

        $approverRoleName = match ($approver->role) {
            'line_manager' => 'Line Manager',
            'manager' => 'Manager',
            'admin' => 'Admin',
            default => 'Manager',
        };

        return [
            'success' => true,
            'new_status' => $newStatus,
            'message' => "Campaign successfully approved by {$approverRoleName} and queued for dispatch.",
        ];
    }

    /**
     * Executes rejection of a campaign.
     *
     * @param Campaign $campaign
     * @param User $rejecter
     * @param string|null $reason
     * @return array{success: bool, message: string, new_status?: string, error?: string}
     */
    public function reject(Campaign $campaign, User $rejecter, ?string $reason = null): array
    {
        if (!$this->canReject($campaign, $rejecter)) {
            return [
                'success' => false,
                'error' => 'Unauthorized: You do not have permission to reject this campaign.',
            ];
        }

        $previousStatus = $campaign->status;
        $now = Carbon::now();
        $reasonText = trim((string)$reason) ?: 'Campaign rejected by approver';

        $campaign->update([
            'status' => 'rejected',
            'current_approver_id' => null,
            'rejected_by' => $rejecter->id,
            'rejection_reason' => $reasonText,
            'rejected_at' => $now,
            'approved_by' => $rejecter->id,
            'approval_remark' => $reasonText,
            'approval_at' => $now,
        ]);

        // Mark Recipient Logs as blocked
        RecipientLog::where('campaign_id', $campaign->id)
            ->whereIn('delivery_status', ['pending_approval', 'pending_line_manager', 'pending_manager'])
            ->update([
                'delivery_status' => 'blocked',
                'decision' => 'blocked',
                'decision_reason' => 'CAMPAIGN_REJECTED',
            ]);

        // Record in Campaign Approval History
        CampaignApproval::create([
            'campaign_id' => $campaign->id,
            'approver_id' => $rejecter->id,
            'approver_role' => $rejecter->role,
            'action' => 'rejected',
            'previous_status' => $previousStatus,
            'new_status' => 'rejected',
            'comment' => $reasonText,
        ]);

        return [
            'success' => true,
            'new_status' => 'rejected',
            'message' => 'Campaign has been rejected.',
        ];
    }

    /**
     * Server-side check: Verifies if a campaign is fully approved for email sending.
     */
    public function isFullyApproved(Campaign $campaign): bool
    {
        if (in_array($campaign->status, ['draft', 'pending_approval', 'pending_line_manager', 'pending_manager', 'rejected', 'failed'])) {
            return false;
        }

        return in_array($campaign->status, ['approved', 'queued', 'sending', 'scheduled', 'completed']);
    }

    public function getStatusLabel(Campaign|string $statusOrCampaign): string
    {
        if ($statusOrCampaign instanceof Campaign) {
            $campaign = $statusOrCampaign;
            if ($campaign->status === 'pending_approval') {
                if ($campaign->currentApprover && $campaign->currentApprover->role === 'line_manager') {
                    return 'Pending Line Manager Approval';
                }
                if ($campaign->currentApprover && $campaign->currentApprover->role === 'manager') {
                    return 'Pending Manager Approval';
                }
                return 'Pending Approval';
            }
            $status = $campaign->status;
        } else {
            $status = $statusOrCampaign;
        }

        return match ($status) {
            'pending_line_manager' => 'Pending Line Manager Approval',
            'pending_manager' => 'Pending Manager Approval',
            'pending_approval' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'queued' => 'Queued for Dispatch',
            'sending' => 'Sending',
            'scheduled' => 'Scheduled',
            'completed' => 'Dispatched',
            'failed' => 'Failed',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
