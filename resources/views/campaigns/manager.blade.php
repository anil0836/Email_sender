@extends('layouts.app')

@section('page_title', 'Team Campaigns & Approvals')

@section('content')
<div class="row g-4">
    <!-- Filter Bar -->
    <div class="col-12">
        <div class="card">
            <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2.5 flex-wrap">
                    <span class="stat-label mb-0">Team Member:</span>
                    <select id="team-member-select" class="form-select form-select-sm" style="width: 180px;" onchange="loadTeamCampaigns()">
                        <option value="">-- All Team Members --</option>
                    </select>

                    <span class="stat-label mb-0 ms-2">Status:</span>
                    <select id="status-select" class="form-select form-select-sm" style="width: 160px;" onchange="loadTeamCampaigns()">
                        <option value="">-- All Statuses --</option>
                        <option value="pending_approval">Pending Approval</option>
                        <option value="rejected">Rejected</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="queued">Queued / Sending</option>
                        <option value="completed">Dispatched</option>
                    </select>
                </div>
                <div>
                    <button class="btn btn-outline-secondary btn-sm" onclick="loadTeamCampaigns()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaigns List -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold text-zinc-900"><i class="bi bi-card-checklist me-1.5 text-zinc-500"></i> Team Campaign Submissions</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive border-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Subject & Campaign ID</th>
                                <th>Sender</th>
                                <th>Submitted</th>
                                <th>Recipients</th>
                                <th>Status</th>
                                <th>Approval Audit</th>
                                <th class="text-center pe-4" style="width: 200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="campaigns-table-body">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm text-zinc-400 me-2" role="status"></div>
                                    Loading team campaigns...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectCampaignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-3 px-4" style="border-bottom: 1px solid var(--border-color);">
                <h6 class="modal-title fw-semibold text-zinc-900"><i class="bi bi-x-circle text-danger me-1.5"></i> Reject Campaign</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.75rem;"></button>
            </div>
            <div class="modal-body py-3 px-4">
                <input type="hidden" id="reject-campaign-id">
                <div class="mb-3">
                    <label for="reject-remark" class="form-label mb-1">Provide Rejection Reason (Audit Remark):</label>
                    <textarea class="form-control" id="reject-remark" rows="4" placeholder="Explain why the campaign was rejected..." required></textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 p-3 border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="submitRejection()">Confirm Rejection</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('extra_scripts')
<script>
    let rejectModalInstance = null;

    function loadTeamMembers() {
        fetch('/api/manager/team-members')
            .then(res => res.json())
            .then(members => {
                const select = document.getElementById('team-member-select');
                select.innerHTML = '<option value="">-- All Team Members --</option>';
                members.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = `${m.username} (${m.emp_id || 'N/A'})`;
                    select.appendChild(opt);
                });
            })
            .catch(err => console.error("Error loading team members:", err));
    }

    function loadTeamCampaigns() {
        const memberId = document.getElementById('team-member-select').value;
        const statusFilter = document.getElementById('status-select').value;
        let url = '/api/manager/campaigns';
        if (memberId) {
            url += `?team_member_id=${memberId}`;
        }

        fetch(url)
            .then(res => res.json())
            .then(campaigns => {
                const tbody = document.getElementById('campaigns-table-body');
                
                if (statusFilter) {
                    campaigns = campaigns.filter(c => {
                        if (statusFilter === 'queued') {
                            return c.status === 'queued' || c.status === 'sending';
                        }
                        return c.status === statusFilter;
                    });
                }
                
                if (campaigns.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No campaigns submitted by team members matching the filter criteria found.</td></tr>';
                    return;
                }

                let html = '';
                campaigns.forEach(c => {
                    let statusBadge = '';
                    let actionHtml = '';
                    
                    if (c.status === 'pending_approval') {
                        statusBadge = '<span class="badge bg-warning-soft"><i class="bi bi-clock me-1"></i>Pending Approval</span>';
                        actionHtml = `
                            <div class="d-flex gap-1 justify-content-center">
                                <button class="btn btn-outline-secondary btn-xs text-emerald-700" onclick="approveCampaign('${c.id}')"><i class="bi bi-check-lg"></i> Approve</button>
                                <button class="btn btn-outline-danger btn-xs" onclick="openRejectModal('${c.id}')"><i class="bi bi-x"></i> Reject</button>
                            </div>
                        `;
                    } else if (c.status === 'rejected') {
                        statusBadge = '<span class="badge bg-danger-soft"><i class="bi bi-x-circle me-1"></i>Rejected</span>';
                    } else if (c.status === 'scheduled') {
                        statusBadge = '<span class="badge bg-primary-soft"><i class="bi bi-clock me-1"></i>Scheduled</span>';
                    } else if (c.status === 'queued' || c.status === 'sending') {
                        statusBadge = '<span class="badge bg-primary-soft"><i class="bi bi-send me-1"></i>Queued</span>';
                    } else if (c.status === 'completed') {
                        statusBadge = '<span class="badge bg-success-soft"><i class="bi bi-check-circle me-1"></i>Dispatched</span>';
                    } else {
                        statusBadge = `<span class="badge bg-secondary-soft">${c.status}</span>`;
                    }

                    if (!actionHtml) {
                        actionHtml = `
                            <div class="text-center">
                                <span class="text-zinc-400" style="font-size: 0.76rem;">Reviewed</span>
                            </div>
                        `;
                    }

                    actionHtml += `
                        <div class="text-center mt-1">
                            <a class="btn btn-outline-secondary btn-xs" href="/campaign/${c.id}"><i class="bi bi-eye"></i> Details</a>
                        </div>
                    `;

                    let auditText = '<span class="text-zinc-400">-</span>';
                    if (c.approver_username) {
                        const dateStr = new Date(c.approval_at).toLocaleString();
                        const actionName = c.status === 'rejected' ? 'Rejected' : 'Approved';
                        const badgeColor = c.status === 'rejected' ? 'text-rose-700' : 'text-emerald-700';
                        
                        auditText = `
                            <div class="text-zinc-700" style="font-size: 0.775rem;">
                                <strong>${actionName}:</strong> ${escapeHtml(c.approver_username)}
                                <div class="text-zinc-400" style="font-size: 0.72rem;">${dateStr}</div>
                                ${c.approval_remark ? `<div class="${badgeColor} fw-medium mt-0.5">Remark: <span class="text-zinc-600">${escapeHtml(c.approval_remark)}</span></div>` : ''}
                            </div>
                        `;
                    }

                    const schedStr = c.scheduled_at 
                        ? `<div class="text-zinc-500 font-monospace mt-0.5" style="font-size: 0.72rem;"><i class="bi bi-clock me-1"></i>Sched: ${new Date(c.scheduled_at).toLocaleString()}</div>` 
                        : '';

                    html += `
                        <tr>
                            <td class="ps-4">
                                <div class="fw-semibold text-zinc-900">${escapeHtml(c.subject)}</div>
                                <div class="text-zinc-400 font-monospace" style="font-size: 0.72rem;">ID: ${c.id}</div>
                                ${schedStr}
                            </td>
                            <td>
                                <div class="fw-medium text-zinc-900">${escapeHtml(c.username)}</div>
                                <div class="text-zinc-400" style="font-size: 0.72rem;">Emp: ${escapeHtml(c.emp_id || 'N/A')}</div>
                            </td>
                            <td class="text-zinc-500" style="font-size: 0.76rem;">${new Date(c.created_at).toLocaleString()}</td>
                            <td class="font-monospace" style="font-size: 0.76rem;">
                                <span class="text-zinc-900 fw-semibold">${c.total_requested}</span> total &bull; <span class="text-emerald-700">${c.total_approved} app</span>
                            </td>
                            <td>${statusBadge}</td>
                            <td>${auditText}</td>
                            <td class="pe-4">${actionHtml}</td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            })
            .catch(err => {
                console.error("Error loading team campaigns:", err);
                const tbody = document.getElementById('campaigns-table-body');
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Failed to load team campaigns.</td></tr>';
            });
    }

    function approveCampaign(campaignId) {
        if (!confirm("Are you sure you want to approve this campaign for sending?")) {
            return;
        }

        const remark = prompt("Provide an optional approval remark:", "Approved");
        if (remark === null) return;

        fetch('/api/campaign/approve', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ campaign_id: campaignId, decision: 'approve', remark })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                loadTeamCampaigns();
            } else {
                alert(data.error || "Failed to approve campaign.");
            }
        })
        .catch(err => console.error("Error approving campaign:", err));
    }

    function openRejectModal(campaignId) {
        document.getElementById('reject-campaign-id').value = campaignId;
        document.getElementById('reject-remark').value = '';
        if (!rejectModalInstance) {
            rejectModalInstance = new bootstrap.Modal(document.getElementById('rejectCampaignModal'));
        }
        rejectModalInstance.show();
    }

    function submitRejection() {
        const campaignId = document.getElementById('reject-campaign-id').value;
        const remark = document.getElementById('reject-remark').value.trim();

        if (!remark) {
            alert("Rejection reason/remark is required.");
            return;
        }

        fetch('/api/campaign/approve', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ campaign_id: campaignId, decision: 'reject', remark })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                rejectModalInstance.hide();
                loadTeamCampaigns();
            } else {
                alert(data.error || "Failed to reject campaign.");
            }
        })
        .catch(err => console.error("Error rejecting campaign:", err));
    }

    function escapeHtml(text) {
        return text
            ? text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;")
            : '';
    }

    window.onload = function() {
        loadTeamMembers();
        loadTeamCampaigns();
    };
</script>
@endsection
