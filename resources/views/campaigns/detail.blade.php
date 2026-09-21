@extends('layouts.app')

@section('page_title', 'Campaign Audit & Telemetry')

@section('content')
<style>
    .stat-number-link {
        cursor: pointer;
        display: inline-block;
        transition: transform 0.15s ease, opacity 0.15s ease;
    }
    .stat-number-link:hover {
        opacity: 0.8;
        transform: scale(1.06);
        text-decoration: underline !important;
    }

    .campaign-sidebar-card {
        border-radius: var(--radius-card);
        background: #ffffff;
        border: 1px solid var(--border-color);
        transition: all 0.2s ease;
    }

    .campaign-sidebar-item {
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.15s ease;
        text-decoration: none !important;
        display: block;
    }

    .campaign-sidebar-item:hover {
        background-color: #f4f4f5 !important;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
    }

    .campaign-sidebar-item.active-campaign {
        background-color: var(--active-pill-bg, #eef2ff) !important;
        border-color: var(--active-pill-border, #c7d2fe) !important;
        box-shadow: 0 1px 4px rgba(79, 70, 229, 0.08);
    }

    .sidebar-tab-btn {
        font-size: 0.75rem;
        padding: 4px 8px;
        border-radius: 6px;
        border: none;
        background: transparent;
        color: #71717a;
        font-weight: 500;
        transition: all 0.15s ease;
    }

    .sidebar-tab-btn.active {
        background: #ffffff !important;
        color: #09090b !important;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
    }
</style>

<div class="row g-4">
    <!-- Left: Campaigns Navigator Sidebar (Pending & Approved) -->
    <div class="col-xl-3 col-lg-4 col-12" id="campaigns-sidebar-col">
        <div class="card h-100 border" style="border-radius: var(--radius-card);">
            <!-- Sidebar Header & Toggle -->
            <div class="card-header bg-white py-3 px-3 d-flex justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-zinc-900" style="font-size: 0.875rem;">
                        <i class="bi bi-layout-sidebar-inset text-primary me-1"></i> Campaigns
                    </span>
                    <span class="badge bg-secondary-soft text-zinc-700" id="sidebar-total-badge">{{ count($sidebarCampaigns ?? []) }}</span>
                </div>
                <a href="{{ route('campaign_list_view') }}" class="btn btn-outline-secondary btn-xs" title="View Full List">
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>
            </div>

            <div class="p-2 border-bottom bg-light">
                <!-- Search Box -->
                <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text bg-white border-end-0 text-zinc-400"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="campaign-search-input" placeholder="Search campaigns..." oninput="filterCampaignsList()">
                    <button type="button" class="btn btn-outline-secondary border-start-0 d-none" id="btn-clear-search" onclick="clearCampaignSearch()"><i class="bi bi-x"></i></button>
                </div>

                <!-- Tabs: Pending / Approved / All -->
                <div class="sidebar-tab-pills d-flex p-1 bg-white rounded border">
                    <button type="button" class="sidebar-tab-btn flex-fill" id="tab-btn-pending" onclick="setSidebarFilter('pending')">
                        Pending (<span id="count-pending-badge">0</span>)
                    </button>
                    <button type="button" class="sidebar-tab-btn flex-fill" id="tab-btn-approved" onclick="setSidebarFilter('approved')">
                        Approved (<span id="count-approved-badge">0</span>)
                    </button>
                    <button type="button" class="sidebar-tab-btn flex-fill active" id="tab-btn-all" onclick="setSidebarFilter('all')">
                        All (<span id="count-all-badge">{{ count($sidebarCampaigns ?? []) }}</span>)
                    </button>
                </div>
            </div>

            <!-- Campaigns List Container -->
            <div class="card-body p-2" style="max-height: 600px; overflow-y: auto;" id="sidebar-campaigns-list">
                @if(isset($sidebarCampaigns) && count($sidebarCampaigns) > 0)
                    @foreach($sidebarCampaigns as $c)
                        <div class="campaign-sidebar-item p-2 mb-1.5 border {{ ($c->id === ($campaign_id ?? $campaignId ?? '')) ? 'active-campaign' : 'bg-white border-zinc-200' }}"
                             onclick="if ('{{ $c->id }}' !== campaignId) { window.location.href = '/campaign/{{ $c->id }}'; }">
                            <div class="d-flex justify-content-between align-items-start gap-1 mb-1">
                                <div class="text-truncate {{ ($c->id === ($campaign_id ?? $campaignId ?? '')) ? 'fw-bold text-indigo-950' : 'fw-semibold text-zinc-900' }}"
                                     style="font-size: 0.8rem;" title="{{ $c->subject }}">
                                    {{ $c->subject }}
                                </div>
                                @if($c->status === 'pending_approval')
                                    <span class="badge bg-warning-soft text-amber-900 flex-shrink-0" style="font-size: 0.65rem;"><i class="bi bi-clock me-0.5"></i> Pending</span>
                                @elseif($c->status === 'queued')
                                    <span class="badge bg-primary-soft text-indigo-700 flex-shrink-0" style="font-size: 0.65rem;"><i class="bi bi-send me-0.5"></i> Queued</span>
                                @elseif($c->status === 'sending')
                                    <span class="badge bg-primary-soft text-indigo-700 flex-shrink-0" style="font-size: 0.65rem;"><i class="bi bi-arrow-repeat me-0.5"></i> Sending</span>
                                @elseif($c->status === 'completed')
                                    <span class="badge bg-success-soft text-emerald-700 flex-shrink-0" style="font-size: 0.65rem;"><i class="bi bi-check2-circle me-0.5"></i> Approved</span>
                                @elseif($c->status === 'scheduled')
                                    <span class="badge bg-secondary-soft text-zinc-700 flex-shrink-0" style="font-size: 0.65rem;"><i class="bi bi-calendar me-0.5"></i> Sched</span>
                                @elseif($c->status === 'rejected')
                                    <span class="badge bg-danger-soft text-rose-700 flex-shrink-0" style="font-size: 0.65rem;"><i class="bi bi-x-circle me-0.5"></i> Rejected</span>
                                @else
                                    <span class="badge bg-secondary-soft text-zinc-600 flex-shrink-0" style="font-size: 0.65rem;">{{ $c->status }}</span>
                                @endif
                            </div>
                            <div class="d-flex justify-content-between align-items-center text-zinc-500" style="font-size: 0.7rem;">
                                <span>{{ $c->total_requested }} recipients</span>
                                <span>{{ $c->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-4 text-muted" style="font-size: 0.78rem;">No campaigns found.</div>
                @endif
            </div>

            <div class="card-footer bg-white py-2 px-3 border-top text-zinc-500" style="font-size: 0.72rem;">
                <span id="sidebar-status-summary">Showing {{ count($sidebarCampaigns ?? []) }} campaigns</span>
            </div>
        </div>
    </div>

    <!-- Right: Campaign Telemetry & Details -->
    <div class="col-xl-9 col-lg-8 col-12" id="campaign-main-col">
        <div class="row g-4">
            <!-- Approval Banner Alert -->
            <div class="col-12" id="approval-alert-container"></div>

            <!-- Manager Approval Actions Panel -->
            <div class="col-12 d-none" id="manager-approval-card">
                <div class="card border-warning-subtle" style="background-color: #fffbeb;">
                    <div class="card-body p-4">
                        <h6 class="fw-semibold text-amber-900 mb-1.5"><i
                                class="bi bi-shield-check me-1.5 text-amber-700"></i> Manager Authorization Required
                        </h6>
                        <p class="text-zinc-600 mb-3" style="font-size: 0.8125rem;">This campaign was created by team
                            member <strong><span id="camp-sender-username">...</span></strong> (<span
                                id="camp-sender-empid">...</span>) and requires authorization prior to queue dispatch.
                        </p>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-primary btn-sm" onclick="managerDecision('approve')"><i
                                    class="bi bi-check-lg"></i> Approve Campaign</button>
                            <button class="btn btn-outline-danger btn-sm" onclick="managerDecision('reject')"><i
                                    class="bi bi-x"></i> Reject Campaign</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Campaign Summary Card -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1.5">
                                    <h5 class="fw-semibold text-zinc-900 mb-0" id="camp-subject">Loading Subject...</h5>
                                    <span id="camp-status-badge" class="badge bg-secondary-soft">Draft</span>
                                </div>
                                <p class="text-zinc-500 mb-3" id="camp-meta" style="font-size: 0.775rem;">Loading
                                    Metadata...</p>

                                <div id="camp-attachments-container"
                                    class="mb-3 d-flex flex-wrap align-items-center gap-1.5"></div>

                                <div class="d-flex align-items-center gap-3">
                                    <div class="progress" style="width: 140px; height: 6px; border-radius: 999px;"
                                        id="camp-progress-container">
                                        <div class="progress-bar" id="camp-progress-bar" role="progressbar"
                                            style="width: 0%; background-color: #09090b;"></div>
                                    </div>
                                    <small class="text-zinc-500 font-monospace" id="camp-progress-text"
                                        style="font-size: 0.75rem;">0% Complete</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button class="btn btn-outline-secondary btn-sm" id="btn-toggle-sidebar"
                                    onclick="toggleSidebarView()" title="Toggle Campaigns Sidebar">
                                    <i class="bi bi-layout-sidebar-inset"></i> <span id="toggle-sidebar-label">Hide
                                        Sidebar</span>
                                </button>
                                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-arrow-left"></i> Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats summary cards -->
            <div class="col-12">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="stat-card" style="cursor: pointer;" onclick="showStatEmailsModal('requested')"
                            title="Click to view recipient emails">
                            <span class="stat-label">Total Requested</span>
                            <div class="stat-value text-zinc-900"><a href="javascript:void(0)"
                                    onclick="event.stopPropagation(); showStatEmailsModal('requested')"
                                    class="text-decoration-none text-zinc-900 stat-number-link" id="sum-requested">0</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card" style="cursor: pointer;" onclick="showStatEmailsModal('approved')"
                            title="Click to view recipient emails">
                            <span class="stat-label text-emerald-700">Approved & Sent</span>
                            <div class="stat-value text-emerald-700"><a href="javascript:void(0)"
                                    onclick="event.stopPropagation(); showStatEmailsModal('approved')"
                                    class="text-decoration-none text-emerald-700 stat-number-link"
                                    id="sum-approved">0</a></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card" style="cursor: pointer;" onclick="showStatEmailsModal('blocked')"
                            title="Click to view recipient emails">
                            <span class="stat-label text-rose-700">Blocked (Ineligible)</span>
                            <div class="stat-value text-rose-700"><a href="javascript:void(0)"
                                    onclick="event.stopPropagation(); showStatEmailsModal('blocked')"
                                    class="text-decoration-none text-rose-700 stat-number-link" id="sum-blocked">0</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card" style="cursor: pointer;" onclick="showStatEmailsModal('delivered')"
                            title="Click to view recipient emails">
                            <span class="stat-label">Delivered Count</span>
                            <div class="stat-value text-zinc-900"><a href="javascript:void(0)"
                                    onclick="event.stopPropagation(); showStatEmailsModal('delivered')"
                                    class="text-decoration-none text-zinc-900 stat-number-link" id="sum-delivered">0</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manager Stats & Compliance Audit -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold text-zinc-900"><i
                                class="bi bi-shield-lock me-1.5 text-zinc-500"></i> Send-Time Compliance Audit Summary
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3 col-6">
                                <div class="p-3 rounded-3 bg-zinc-50 border text-center stat-card"
                                    style="cursor: pointer;" onclick="showStatEmailsModal('suppression')"
                                    title="Click to view recipient emails">
                                    <span class="stat-label d-block text-rose-700 mb-1">Suppression Blocked</span>
                                    <span class="fs-5 fw-bold d-block"><a href="javascript:void(0)"
                                            onclick="event.stopPropagation(); showStatEmailsModal('suppression')"
                                            class="text-decoration-none text-rose-700 stat-number-link"
                                            id="mngr-stat-spam">0</a></span>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-3 rounded-3 bg-zinc-50 border text-center stat-card"
                                    style="cursor: pointer;" onclick="showStatEmailsModal('owner')"
                                    title="Click to view recipient emails">
                                    <span class="stat-label d-block text-amber-700 mb-1">Different Owner</span>
                                    <span class="fs-5 fw-bold d-block"><a href="javascript:void(0)"
                                            onclick="event.stopPropagation(); showStatEmailsModal('owner')"
                                            class="text-decoration-none text-amber-700 stat-number-link"
                                            id="mngr-stat-owner">0</a></span>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-3 rounded-3 bg-zinc-50 border text-center stat-card"
                                    style="cursor: pointer;" onclick="showStatEmailsModal('optout')"
                                    title="Click to view recipient emails">
                                    <span class="stat-label d-block text-amber-700 mb-1">Salesforce Opt-Out</span>
                                    <span class="fs-5 fw-bold d-block"><a href="javascript:void(0)"
                                            onclick="event.stopPropagation(); showStatEmailsModal('optout')"
                                            class="text-decoration-none text-amber-700 stat-number-link"
                                            id="mngr-stat-optout">0</a></span>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-3 rounded-3 bg-zinc-50 border text-center stat-card"
                                    style="cursor: pointer;" onclick="showStatEmailsModal('other_blocks')"
                                    title="Click to view recipient emails">
                                    <span class="stat-label d-block mb-1">Other Blocks</span>
                                    <span class="fs-5 fw-bold d-block"><a href="javascript:void(0)"
                                            onclick="event.stopPropagation(); showStatEmailsModal('other_blocks')"
                                            class="text-decoration-none text-zinc-900 stat-number-link"
                                            id="mngr-stat-other">0</a></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Campaign Body Content Preview Card -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold text-zinc-900"><i
                                class="bi bi-file-earmark-richtext me-1.5 text-zinc-500"></i> Rendered Email Body</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="p-3 bg-zinc-50 rounded-3 border text-zinc-800"
                            style="min-height: 80px; font-size: 0.85rem; max-height: 300px; overflow-y: auto;"
                            id="camp-body-content">
                            <!-- Populated dynamically -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recipient Logs & Transaction JSON Payload -->
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold text-zinc-900"><i class="bi bi-people me-1.5 text-zinc-500"></i>
                            Recipient-Level Telemetry Logs</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive border-0" style="max-height: 480px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Recipient</th>
                                        <th>Decision</th>
                                        <th>Delivery Status</th>
                                        <th class="pe-4">Provider Message ID</th>
                                    </tr>
                                </thead>
                                <tbody id="recipient-logs-table-body">
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Loading audit logs...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold text-zinc-900"><i class="bi bi-code-slash me-1.5 text-zinc-500"></i>
                            Transaction Audit Payload</span>
                        <button class="btn btn-outline-secondary btn-xs" onclick="copyPayload()"><i
                                class="bi bi-copy"></i> Copy JSON</button>
                    </div>
                    <div class="card-body p-3">
                        <pre class="bg-zinc-50 p-3 rounded-3 text-zinc-700 border mb-0 font-monospace"
                            style="font-size: 0.72rem; max-height: 440px; overflow-y: auto;"
                            id="audit-payload-json">{}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Drilldown Recipient Emails Modal -->
    <div class="modal fade" id="statEmailsModal" tabindex="-1" aria-labelledby="statEmailsModalTitle"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="modal-title fw-semibold text-zinc-900 mb-0" id="statEmailsModalTitle">Recipient
                            Emails</h6>
                        <span class="badge bg-primary-soft text-indigo-700 fw-semibold" id="statEmailsModalCount">0
                            Emails</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Search Box -->
                    <div class="mb-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-zinc-400"><i
                                    class="bi bi-search"></i></span>
                            <input type="text" id="statEmailsSearchInput" class="form-control border-start-0 ps-0"
                                placeholder="Filter emails, record IDs, or reasons..." oninput="filterModalEmails()">
                        </div>
                    </div>

                    <!-- Recipient Table -->
                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Recipient Email</th>
                                    <th>CRM ID</th>
                                    <th>Owner ID</th>
                                    <th>Decision & Status</th>
                                </tr>
                            </thead>
                            <tbody id="statEmailsModalTableBody">
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No emails loaded.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2 px-4 bg-zinc-50 border-top d-flex justify-content-between">
                    <small class="text-zinc-500" id="statEmailsModalSummary">Showing 0 recipients</small>
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                        data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endsection

    @section('extra_scripts')
    <script>
        const campaignId = "{{ $campaign_id ?? $campaignId ?? '' }}";
    let pollInterval = null;
    let currentCampaignLogs = [];
    let currentFilteredEmails = [];

    // Interactive Sidebar Campaigns State
    const serverSidebarData = @json($sidebarCampaigns ?? []);
    let allCampaignsList = Array.isArray(serverSidebarData) ? serverSidebarData : [];
    let currentSidebarFilter = localStorage.getItem('campaign_sidebar_filter') || '';
    let currentSearchQuery = '';

    function initSidebar() {
        if (!currentSidebarFilter) {
            const cur = allCampaignsList.find(c => c.id === campaignId);
            if (cur && cur.status === 'pending_approval') {
                currentSidebarFilter = 'pending';
            } else {
                currentSidebarFilter = 'approved';
            }
        }
        setSidebarFilter(currentSidebarFilter, false);

        const savedVisibility = localStorage.getItem('campaign_sidebar_visible');
        if (savedVisibility === 'hidden') {
            applySidebarVisibility(false);
        }
    }

    function setSidebarFilter(filter, save = true) {
        currentSidebarFilter = filter;
        if (save) {
            localStorage.setItem('campaign_sidebar_filter', filter);
        }

        ['pending', 'approved', 'all'].forEach(f => {
            const btn = document.getElementById(`tab-btn-${f}`);
            if (btn) {
                if (f === filter) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            }
        });

        renderSidebarCampaigns();
    }

    function filterCampaignsList() {
        const input = document.getElementById('campaign-search-input');
        currentSearchQuery = (input.value || '').trim().toLowerCase();
        const clearBtn = document.getElementById('btn-clear-search');
        if (clearBtn) {
            if (currentSearchQuery) {
                clearBtn.classList.remove('d-none');
            } else {
                clearBtn.classList.add('d-none');
            }
        }
        renderSidebarCampaigns();
    }

    function clearCampaignSearch() {
        const input = document.getElementById('campaign-search-input');
        if (input) input.value = '';
        currentSearchQuery = '';
        const clearBtn = document.getElementById('btn-clear-search');
        if (clearBtn) clearBtn.classList.add('d-none');
        renderSidebarCampaigns();
    }

    function toggleSidebarView() {
        const sidebarCol = document.getElementById('campaigns-sidebar-col');
        if (!sidebarCol) return;
        const isCurrentlyHidden = sidebarCol.classList.contains('d-none');
        applySidebarVisibility(isCurrentlyHidden);
        localStorage.setItem('campaign_sidebar_visible', isCurrentlyHidden ? 'visible' : 'hidden');
    }

    function applySidebarVisibility(show) {
        const sidebarCol = document.getElementById('campaigns-sidebar-col');
        const mainCol = document.getElementById('campaign-main-col');
        const label = document.getElementById('toggle-sidebar-label');
        if (!sidebarCol || !mainCol) return;

        if (!show) {
            sidebarCol.classList.add('d-none');
            mainCol.className = 'col-12';
            if (label) label.innerText = 'Show Sidebar';
        } else {
            sidebarCol.classList.remove('d-none');
            mainCol.className = 'col-xl-9 col-lg-8 col-12';
            if (label) label.innerText = 'Hide Sidebar';
        }
    }

    function formatTimeAgo(dateStr) {
        if (!dateStr) return '';
        const dt = new Date(dateStr);
        if (isNaN(dt.getTime())) return '';
        const now = new Date();
        const diffMs = now - dt;
        const diffMin = Math.floor(diffMs / 60000);
        if (diffMin < 1) return 'Just now';
        if (diffMin < 60) return `${diffMin}m ago`;
        const diffHrs = Math.floor(diffMin / 60);
        if (diffHrs < 24) return `${diffHrs}h ago`;
        const diffDays = Math.floor(diffHrs / 24);
        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 7) return `${diffDays}d ago`;
        return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    function renderSidebarCampaigns() {
        const listContainer = document.getElementById('sidebar-campaigns-list');
        if (!listContainer) return;

        let pendingCount = 0;
        let approvedCount = 0;

        allCampaignsList.forEach(c => {
            if (c.status === 'pending_approval') {
                pendingCount++;
            } else if (c.status !== 'rejected') {
                approvedCount++;
            }
        });

        const countPendingBadge = document.getElementById('count-pending-badge');
        const countApprovedBadge = document.getElementById('count-approved-badge');
        const countAllBadge = document.getElementById('count-all-badge');
        const totalBadge = document.getElementById('sidebar-total-badge');

        if (countPendingBadge) countPendingBadge.innerText = pendingCount;
        if (countApprovedBadge) countApprovedBadge.innerText = approvedCount;
        if (countAllBadge) countAllBadge.innerText = allCampaignsList.length;
        if (totalBadge) totalBadge.innerText = allCampaignsList.length;

        let filtered = allCampaignsList.filter(c => {
            if (currentSidebarFilter === 'pending') {
                if (c.status !== 'pending_approval') return false;
            } else if (currentSidebarFilter === 'approved') {
                if (c.status === 'pending_approval' || c.status === 'rejected') return false;
            }

            if (currentSearchQuery) {
                const subj = (c.subject || '').toLowerCase();
                const user = (c.user && c.user.username ? c.user.username : (c.creator_username || '')).toLowerCase();
                if (!subj.includes(currentSearchQuery) && !user.includes(currentSearchQuery)) {
                    return false;
                }
            }

            return true;
        });

        const statusSummary = document.getElementById('sidebar-status-summary');
        if (statusSummary) {
            statusSummary.innerText = `Showing ${filtered.length} of ${allCampaignsList.length}`;
        }

        if (filtered.length === 0) {
            let emptyMsg = 'No campaigns found.';
            if (currentSearchQuery) {
                emptyMsg = `No campaigns match "${escapeHtml(currentSearchQuery)}"`;
            } else if (currentSidebarFilter === 'pending') {
                emptyMsg = 'No pending approval campaigns.';
            } else if (currentSidebarFilter === 'approved') {
                emptyMsg = 'No approved campaigns found.';
            }
            listContainer.innerHTML = `
                <div class="text-center py-4 px-2 text-muted" style="font-size: 0.78rem;">
                    <i class="bi bi-inbox fs-4 d-block text-zinc-300 mb-1"></i>
                    ${emptyMsg}
                </div>
            `;
            return;
        }

        let html = '';
        filtered.forEach(c => {
            const isCurrent = (c.id === campaignId);

            let badgeHtml = '';
            if (c.status === 'pending_approval') {
                badgeHtml = '<span class="badge bg-warning-soft text-amber-900 flex-shrink-0" style="font-size: 0.65rem;"><i class="bi bi-clock me-0.5"></i> Pending</span>';
            } else if (c.status === 'queued') {
                badgeHtml = '<span class="badge bg-primary-soft text-indigo-700 flex-shrink-0" style="font-size: 0.65rem;"><i class="bi bi-send me-0.5"></i> Queued</span>';
            } else if (c.status === 'sending') {
                badgeHtml = '<span class="badge bg-primary-soft text-indigo-700 flex-shrink-0" style="font-size: 0.65rem;"><i class="bi bi-arrow-repeat me-0.5"></i> Sending</span>';
            } else if (c.status === 'completed') {
                badgeHtml = '<span class="badge bg-success-soft text-emerald-700 flex-shrink-0" style="font-size: 0.65rem;"><i class="bi bi-check2-circle me-0.5"></i> Approved</span>';
            } else if (c.status === 'scheduled') {
                badgeHtml = '<span class="badge bg-secondary-soft text-zinc-700 flex-shrink-0" style="font-size: 0.65rem;"><i class="bi bi-calendar me-0.5"></i> Sched</span>';
            } else if (c.status === 'rejected') {
                badgeHtml = '<span class="badge bg-danger-soft text-rose-700 flex-shrink-0" style="font-size: 0.65rem;"><i class="bi bi-x-circle me-0.5"></i> Rejected</span>';
            } else {
                badgeHtml = `<span class="badge bg-secondary-soft text-zinc-600 flex-shrink-0" style="font-size: 0.65rem;">${escapeHtml(c.status)}</span>`;
            }

            const sender = (c.user && c.user.username) ? c.user.username : (c.creator_username || '');
            const recCount = c.total_requested ?? 0;
            const timeAgo = formatTimeAgo(c.created_at);

            html += `
                <div class="campaign-sidebar-item p-2 mb-1.5 border ${isCurrent ? 'active-campaign' : 'bg-white border-zinc-200'}"
                     onclick="if ('${c.id}' !== campaignId) { window.location.href = '/campaign/${c.id}'; }">
                    <div class="d-flex justify-content-between align-items-start gap-1 mb-1">
                        <div class="text-truncate ${isCurrent ? 'fw-bold text-indigo-950' : 'fw-semibold text-zinc-900'}"
                             style="font-size: 0.8rem;" title="${escapeHtml(c.subject || 'Untitled Campaign')}">
                            ${escapeHtml(c.subject || 'Untitled Campaign')}
                        </div>
                        ${badgeHtml}
                    </div>
                    <div class="d-flex justify-content-between align-items-center text-zinc-500" style="font-size: 0.7rem;">
                        <span class="d-flex align-items-center gap-1">
                            <i class="bi bi-people" style="font-size: 0.68rem;"></i> ${recCount}
                            ${sender ? `<span class="text-zinc-400">&bull; by ${escapeHtml(sender)}</span>` : ''}
                        </span>
                        <span class="text-zinc-400" style="font-size: 0.68rem;">
                            ${timeAgo}
                        </span>
                    </div>
                    ${isCurrent ? `
                    <div class="mt-1 pt-1 border-top border-indigo-200 d-flex justify-content-between align-items-center" style="font-size: 0.68rem;">
                        <span class="text-indigo-700 fw-semibold"><i class="bi bi-eye-fill me-1"></i> Current Viewing</span>
                        <span class="badge bg-white text-indigo-700 border border-indigo-200" style="font-size: 0.62rem;">Active</span>
                    </div>` : ''}
                </div>
            `;
        });

        listContainer.innerHTML = html;
    }

    function refreshSidebarListAsync() {
        fetch('/api/campaigns/dropdown')
            .then(res => res.json())
            .then(data => {
                if (Array.isArray(data) && data.length > 0) {
                    allCampaignsList = data;
                    renderSidebarCampaigns();
                }
            })
            .catch(err => console.debug("Sidebar async sync error:", err));
    }

    function managerDecision(decision) {
        let remark = "";
        if (decision === 'reject') {
            remark = prompt("Explain why the campaign was rejected (rejection reason is required):");
            if (!remark) {
                alert("Rejection reason is required.");
                return;
            }
        } else {
            remark = prompt("Enter an optional approval remark:", "Approved");
            if (remark === null) return;
        }

        fetch('/api/campaign/approve', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ campaign_id: campaignId, decision, remark })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                fetchCampaignDetails();
                refreshSidebarListAsync();
            } else {
                alert(data.error || "Failed to update campaign.");
            }
        })
        .catch(err => console.error("Error updating campaign status:", err));
    }

    function fetchCampaignDetails() {
        fetch(`/api/campaign/${campaignId}`)
            .then(res => res.json())
            .then(data => {
                const c = data.campaign;
                const logs = data.recipient_logs;
                currentCampaignLogs = logs || [];

                // Sync current campaign in sidebar list if status updated
                if (c && allCampaignsList) {
                    const idx = allCampaignsList.findIndex(item => item.id === c.id);
                    if (idx !== -1) {
                        const existing = allCampaignsList[idx];
                        if (existing.status !== c.status || existing.total_approved !== c.total_approved) {
                            existing.status = c.status;
                            existing.total_approved = c.total_approved;
                            existing.total_requested = c.total_requested;
                            renderSidebarCampaigns();
                        }
                    }
                }

                document.getElementById('camp-subject').innerText = c.subject;
                const dateStr = new Date(c.created_at).toLocaleString();
                const teamName = c.team || 'Unassigned';
                const managerName = c.manager_sf_name || c.manager_local_username || 'None';
                const replyTo = c.reply_to || 'support@b2bexportsllc.com';
                document.getElementById('camp-meta').innerText = `From: ${c.from_address} • Reply-To: ${replyTo} • Domain: ${c.sending_domain} • Team: ${teamName} • Manager: ${managerName} • Created: ${dateStr}`;

                document.getElementById('camp-body-content').innerHTML = c.body || '<i>(No body content)</i>';

                document.getElementById('sum-requested').innerText = c.total_requested;
                document.getElementById('sum-approved').innerText = c.total_approved;
                document.getElementById('sum-blocked').innerText = c.total_blocked;

                const attachDiv = document.getElementById('camp-attachments-container');
                if (c.attachments) {
                    try {
                        const files = JSON.parse(c.attachments);
                        if (files && files.length > 0) {
                            let attachHtml = '<span class="stat-label mb-0 me-1">Attachments:</span>';
                            files.forEach(f => {
                                attachHtml += `
                                    <span class="attachment-tag">
                                        <i class="bi bi-paperclip text-zinc-400"></i> ${escapeHtml(f)}
                                        <a href="/download/attachment/${campaignId}/${encodeURIComponent(f)}" class="text-zinc-600 hover:text-zinc-900 ms-1" target="_blank"><i class="bi bi-download"></i></a>
                                    </span>
                                `;
                            });
                            attachDiv.innerHTML = attachHtml;
                        } else {
                            attachDiv.innerHTML = '';
                        }
                    } catch(e) {
                        attachDiv.innerHTML = '';
                    }
                } else {
                    attachDiv.innerHTML = '';
                }

                let deliveredCount = 0;
                let processedCount = 0;
                logs.forEach(l => {
                    if (l.delivery_status in { 'delivered': 1, 'opened': 1, 'unsubscribed': 1 }) {
                        deliveredCount++;
                    }
                    if (l.delivery_status !== 'queued' && l.delivery_status !== 'pending_approval' && l.delivery_status !== 'scheduled') {
                        processedCount++;
                    }
                });
                document.getElementById('sum-delivered').innerText = deliveredCount;

                let spamCount = 0;
                let notOwnerCount = 0;
                let optOutCount = 0;
                let otherBlockCount = 0;

                logs.forEach(l => {
                    if (l.decision === 'blocked') {
                        if (l.decision_reason === 'GLOBAL_SUPPRESSION') spamCount++;
                        else if (l.decision_reason === 'DIFFERENT_OWNER') notOwnerCount++;
                        else if (l.decision_reason === 'EMAIL_OPT_OUT') optOutCount++;
                        else otherBlockCount++;
                    }
                });

                document.getElementById('mngr-stat-spam').innerText = spamCount;
                document.getElementById('mngr-stat-owner').innerText = notOwnerCount;
                document.getElementById('mngr-stat-optout').innerText = optOutCount;
                document.getElementById('mngr-stat-other').innerText = otherBlockCount;

                const alertContainer = document.getElementById('approval-alert-container');
                const approvalCard = document.getElementById('manager-approval-card');
                const currentUserId = {{ session('user_id', Auth::id() ?? 0) }};
                const currentUserRole = "{{ session('role', Auth::user()->role ?? '') }}";

                document.getElementById('camp-sender-username').innerText = c.creator_username || 'Unknown';
                document.getElementById('camp-sender-empid').innerText = c.creator_emp_id || 'N/A';

<<<<<<< HEAD
                if (c.status === 'pending_approval' && (currentUserRole === 'admin' || c.manager_id === currentUserId)) {
=======
                if (c.status === 'pending_approval' &&
                    (currentUserRole === 'admin' ||
                     currentUserRole === 'manager' ||
                     c.manager_user_id === currentUserId ||
                     c.manager_id === currentUserId)) {
>>>>>>> origin/Email0002
                    approvalCard.classList.remove('d-none');
                } else {
                    approvalCard.classList.add('d-none');
                }

                if (c.status === 'pending_approval') {
                    alertContainer.innerHTML = `
                        <div class="p-3 rounded-3 bg-warning-soft border border-warning-subtle d-flex align-items-center gap-2">
                            <i class="bi bi-clock-history text-amber-700"></i>
                            <span class="text-amber-900" style="font-size: 0.8125rem;"><strong>Pending Approval:</strong> This campaign is currently pending manager authorization.</span>
                        </div>
                    `;
                } else if (c.status === 'rejected') {
                    const remarkText = c.approval_remark ? `<br><span class="text-rose-700">Remark: ${escapeHtml(c.approval_remark)}</span>` : '';
                    alertContainer.innerHTML = `
                        <div class="p-3 rounded-3 bg-danger-soft border border-danger-subtle d-flex align-items-center gap-2">
                            <i class="bi bi-x-circle text-rose-700"></i>
                            <span class="text-rose-900" style="font-size: 0.8125rem;"><strong>Rejected Campaign:</strong> Rejected by <strong>${escapeHtml(c.approver_username || 'Unknown')}</strong>.${remarkText}</span>
                        </div>
                    `;
                } else if (c.approved_by) {
                    const remarkText = c.approval_remark ? `<br><span class="text-zinc-600">Remark: ${escapeHtml(c.approval_remark)}</span>` : '';
                    alertContainer.innerHTML = `
                        <div class="p-3 rounded-3 bg-success-soft border border-success-subtle d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle text-emerald-700"></i>
                            <span class="text-emerald-900" style="font-size: 0.8125rem;"><strong>Approved:</strong> Authorized by manager <strong>${escapeHtml(c.approver_username || 'Unknown')}</strong>.${remarkText}</span>
                        </div>
                    `;
                } else {
                    alertContainer.innerHTML = '';
                }

                const bar = document.getElementById('camp-progress-bar');
                const badge = document.getElementById('camp-status-badge');
                
                let progressPercent = c.total_requested > 0 
                    ? Math.round((processedCount / c.total_requested) * 100) 
                    : 0;

                bar.style.width = progressPercent + '%';
                document.getElementById('camp-progress-text').innerText = `${progressPercent}% Complete`;

                if (c.status === 'completed') {
                    badge.className = 'badge bg-success-soft';
                    badge.innerText = 'Completed';
                    bar.style.backgroundColor = '#059669';
                    clearInterval(pollInterval);
                } else if (c.status === 'sending') {
                    badge.className = 'badge bg-primary-soft';
                    badge.innerText = 'Sending';
                } else if (c.status === 'queued') {
                    badge.className = 'badge bg-warning-soft';
                    badge.innerText = 'Queued';
                } else if (c.status === 'pending_approval') {
                    badge.className = 'badge bg-warning-soft';
                    badge.innerText = 'Pending Approval';
                    bar.style.backgroundColor = '#d97706';
                } else if (c.status === 'rejected') {
                    badge.className = 'badge bg-danger-soft';
                    badge.innerText = 'Rejected';
                    bar.style.backgroundColor = '#e11d48';
                    clearInterval(pollInterval);
                } else if (c.status === 'scheduled') {
                    badge.className = 'badge bg-secondary-soft';
                    badge.innerText = 'Scheduled';
                    bar.style.backgroundColor = '#09090b';
                } else {
                    badge.className = 'badge bg-secondary-soft';
                    badge.innerText = 'Draft';
                }

                renderLogs(logs);
                formatAuditPayload(c, logs);
            })
            .catch(err => {
                console.error("Error fetching campaign details:", err);
                clearInterval(pollInterval);
            });
    }

    function showStatEmailsModal(category) {
        if (!currentCampaignLogs || currentCampaignLogs.length === 0) {
            alert("Logs not loaded yet. Please wait...");
            return;
        }

        let title = 'Recipient Emails';
        let filtered = [];

        if (category === 'requested') {
            title = 'Total Requested Recipients';
            filtered = currentCampaignLogs;
        } else if (category === 'approved') {
            title = 'Approved & Sent Recipients';
            filtered = currentCampaignLogs.filter(l => l.decision === 'approved');
        } else if (category === 'blocked') {
            title = 'Blocked (Ineligible) Recipients';
            filtered = currentCampaignLogs.filter(l => l.decision === 'blocked');
        } else if (category === 'delivered') {
            title = 'Delivered Inboxes';
            filtered = currentCampaignLogs.filter(l => l.delivery_status in { 'delivered': 1, 'opened': 1, 'unsubscribed': 1 });
        } else if (category === 'suppression') {
            title = 'Global Suppression Blocked Recipients';
            filtered = currentCampaignLogs.filter(l => l.decision === 'blocked' && l.decision_reason === 'GLOBAL_SUPPRESSION');
        } else if (category === 'owner') {
            title = 'Different Owner Blocked Recipients';
            filtered = currentCampaignLogs.filter(l => l.decision === 'blocked' && l.decision_reason === 'DIFFERENT_OWNER');
        } else if (category === 'optout') {
            title = 'Salesforce Opt-Out Blocked Recipients';
            filtered = currentCampaignLogs.filter(l => l.decision === 'blocked' && l.decision_reason === 'EMAIL_OPT_OUT');
        } else if (category === 'other_blocks') {
            title = 'Other Policy Blocked Recipients';
            filtered = currentCampaignLogs.filter(l => l.decision === 'blocked' && !['GLOBAL_SUPPRESSION', 'DIFFERENT_OWNER', 'EMAIL_OPT_OUT'].includes(l.decision_reason));
        }

        currentFilteredEmails = filtered;
        document.getElementById('statEmailsModalTitle').innerText = title;
        document.getElementById('statEmailsModalCount').innerText = `${filtered.length} Emails`;
        document.getElementById('statEmailsSearchInput').value = '';

        renderModalEmailsTable(filtered);

        const myModal = new bootstrap.Modal(document.getElementById('statEmailsModal'));
        myModal.show();
    }

    function filterModalEmails() {
        const query = (document.getElementById('statEmailsSearchInput').value || '').toLowerCase().trim();
        if (!query) {
            renderModalEmailsTable(currentFilteredEmails);
            return;
        }

        const matches = currentFilteredEmails.filter(l => {
            return (l.email && l.email.toLowerCase().includes(query)) ||
                   (l.salesforce_record_id && l.salesforce_record_id.toLowerCase().includes(query)) ||
                   (l.record_owner_id && l.record_owner_id.toLowerCase().includes(query)) ||
                   (l.decision_reason && l.decision_reason.toLowerCase().includes(query)) ||
                   (l.delivery_status && l.delivery_status.toLowerCase().includes(query));
        });

        renderModalEmailsTable(matches);
    }

    function renderModalEmailsTable(items) {
        const tbody = document.getElementById('statEmailsModalTableBody');
        const summary = document.getElementById('statEmailsModalSummary');
        summary.innerText = `Showing ${items.length} of ${currentFilteredEmails.length} recipients`;

        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No matching recipient emails found.</td></tr>';
            return;
        }

        let html = '';
        items.forEach(l => {
            const decBadge = l.decision === 'approved'
                ? '<span class="badge bg-success-soft">Approved</span>'
                : '<span class="badge bg-danger-soft">Blocked</span>';

            let statusDetail = '';
            if (l.decision === 'blocked') {
                const reasonLabels = {
                    "EMAIL_OPT_OUT": "Salesforce Opt-Out",
                    "GLOBAL_SUPPRESSION": "Global Suppression List",
                    "DIFFERENT_OWNER": "Different Owner ID",
                    "INVALID_EMAIL": "Not in CRM",
                    "INACTIVE_RECORD": "Inactive Record",
                    "MISSING_CONSENT": "Missing GDPR Consent",
                    "COMPLIANCE_RULE": "Duplicate / Policy Block"
                };
                statusDetail = `<div class="text-rose-700 fw-medium" style="font-size: 0.76rem;">${reasonLabels[l.decision_reason] || l.decision_reason}</div>`;
            } else {
                statusDetail = `<div class="text-zinc-600 font-monospace" style="font-size: 0.74rem;">${l.delivery_status}</div>`;
            }

            html += `
                <tr>
                    <td class="ps-3">
                        <div class="fw-semibold text-zinc-900">${escapeHtml(l.email)}</div>
                    </td>
                    <td><code class="text-zinc-600">${escapeHtml(l.salesforce_record_id || 'N/A')}</code></td>
                    <td><span class="text-zinc-700" style="font-size: 0.78rem;">${escapeHtml(l.record_owner_id || 'N/A')}</span></td>
                    <td>
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            ${decBadge}
                            ${statusDetail}
                        </div>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    function renderLogs(logs) {
        const tbody = document.getElementById('recipient-logs-table-body');
        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No recipient logs found.</td></tr>';
            return;
        }

        let html = '';
        logs.forEach(l => {
            const decBadge = l.decision === 'approved'
                ? '<span class="badge bg-success-soft">Approved</span>'
                : '<span class="badge bg-danger-soft">Blocked</span>';

            let statusHtml = '';
            if (l.decision === 'blocked') {
                const reasonLabels = {
                    "EMAIL_OPT_OUT": "Salesforce Opt-Out",
                    "GLOBAL_SUPPRESSION": "Suppressed",
                    "DIFFERENT_OWNER": "Wrong Owner",
                    "INVALID_EMAIL": "Not in CRM",
                    "INACTIVE_RECORD": "Inactive Record",
                    "MISSING_CONSENT": "GDPR Missing Consent"
                };
                statusHtml = `<span class="text-rose-700" style="font-size: 0.775rem;"><i class="bi bi-slash-circle me-1"></i>${reasonLabels[l.decision_reason] || l.decision_reason}</span>`;
            } else {
                const statusBadges = {
                    "pending_approval": '<span class="badge bg-warning-soft">Pending Approval</span>',
                    "queued": '<span class="badge bg-warning-soft">Queued</span>',
                    "sent": '<span class="badge bg-secondary-soft">Sent</span>',
                    "delivered": '<span class="badge bg-success-soft">Delivered</span>',
                    "opened": '<span class="badge bg-secondary-soft text-zinc-900">Opened</span>',
                    "bounce": '<span class="badge bg-danger-soft">Bounced</span>',
                    "spam_complaint": '<span class="badge bg-danger-soft">Spam</span>',
                    "unsubscribed": '<span class="badge bg-secondary-soft">Unsubscribed</span>',
                    "failed": '<span class="badge bg-danger-soft">Failed</span>',
                    "scheduled": '<span class="badge bg-secondary-soft">Scheduled</span>'
                };
                statusHtml = statusBadges[l.delivery_status] || l.delivery_status;
            }

            const errorText = l.error_message 
                ? `<div class="text-rose-600" style="font-size: 0.72rem; margin-top: 2px;"><i class="bi bi-exclamation-triangle"></i> ${escapeHtml(l.error_message)}</div>` 
                : '';

            html += `
                <tr>
                    <td class="ps-4">
                        <div class="fw-semibold text-zinc-900">${escapeHtml(l.email)}</div>
                        <div class="text-zinc-400 font-monospace" style="font-size: 0.72rem;">ID: ${l.salesforce_record_id}</div>
                    </td>
                    <td>${decBadge}</td>
                    <td>${statusHtml}</td>
                    <td class="pe-4">
                        <code class="text-zinc-600" style="font-size: 0.72rem;">${escapeHtml(l.provider_message_id || 'N/A')}</code>
                        ${errorText}
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    function formatAuditPayload(campaign, logs) {
        const payload = {
            "transaction_id": campaign.id,
            "campaign_id": campaign.id,
            "requested_by_user_id": campaign.user_id,
            "requested_at": campaign.created_at,
            "subject": campaign.subject,
            "sending_domain": campaign.sending_domain,
            "from_address": campaign.from_address,
            "reply_to": campaign.reply_to,
            "attachments": campaign.attachments ? JSON.parse(campaign.attachments) : [],
            "total_requested": campaign.total_requested,
            "total_approved": campaign.total_approved,
            "total_blocked": campaign.total_blocked,
            "validation_version": "v1.2-compliance-rules",
            "salesforce_validation_timestamp": campaign.created_at,
            "status": campaign.status,
            "recipients_audit_summary": logs.map(l => ({
                "salesforce_record_id": l.salesforce_record_id,
                "salesforce_object": l.salesforce_object,
                "record_owner_id": l.record_owner_id,
                "decision": l.decision,
                "decision_reason": l.decision_reason,
                "delivery_status": l.delivery_status,
                "validated_at": l.validated_at
            }))
        };

        document.getElementById('audit-payload-json').textContent = JSON.stringify(payload, null, 2);
    }

    function copyPayload() {
        const text = document.getElementById('audit-payload-json').textContent;
        navigator.clipboard.writeText(text).then(() => {
            alert("Audit JSON copied!");
        });
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
<<<<<<< HEAD
        fetchCampaignDetails();
        pollInterval = setInterval(fetchCampaignDetails, 3000);
    };
</script>
@endsection

=======
        initSidebar();
        fetchCampaignDetails();
        pollInterval = setInterval(fetchCampaignDetails, 3000);
    };
    </script>
    @endsection
>>>>>>> origin/Email0002
