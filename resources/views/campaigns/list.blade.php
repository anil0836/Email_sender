@extends('layouts.app')

@section('page_title', 'My Campaigns')

@section('content')
<style>
    .campaign-row {
        transition: background-color 0.12s ease;
    }
    .campaign-row:hover {
        background-color: #fafafa;
    }
    .filter-tab-btn {
        font-size: 0.8125rem;
        font-weight: 500;
        padding: 6px 14px;
        border-radius: 6px;
        border: none;
        background: transparent;
        color: #71717a;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .filter-tab-btn:hover {
        color: #18181b;
        background-color: #f4f4f5;
    }
    .filter-tab-btn.active {
        color: #09090b !important;
        background-color: #ffffff !important;
        font-weight: 600;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }
</style>

@php
    $totalCount = $campaigns->count();
    $pendingCount = $campaigns->whereIn('status', ['pending_approval', 'pending_line_manager', 'pending_manager'])->count();
    $sentCount = $campaigns->whereIn('status', ['queued', 'sending', 'completed'])->count();
    $scheduledCount = $campaigns->where('status', 'scheduled')->count();
    $rejectedCount = $campaigns->where('status', 'rejected')->count();
    $totalRecipientsApproved = $campaigns->sum('total_approved');
@endphp

<div class="row g-4">
    <!-- Page Header & Action -->
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="fw-bold text-zinc-900 mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-envelope-paper-fill" style="color: var(--primary-color, #4f46e5);"></i> My Campaigns
                </h4>
                <p class="text-zinc-500 mb-0" style="font-size: 0.825rem;">
                    Track and monitor all your pending approval and dispatched email campaigns.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('campaign_create_view') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Create Campaign
                </a>
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-grid"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Stat Cards -->
    <div class="col-12">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <span class="stat-label text-zinc-500">Total Campaigns</span>
                    <div class="stat-value text-zinc-900">{{ $totalCount }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card" style="border-left: 3px solid #f59e0b;">
                    <span class="stat-label text-amber-700">Pending Approval</span>
                    <div class="stat-value text-amber-700">{{ $pendingCount }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card" style="border-left: 3px solid #10b981;">
                    <span class="stat-label text-emerald-700">Sent & Queued</span>
                    <div class="stat-value text-emerald-700">{{ $sentCount }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <span class="stat-label text-indigo-700">Recipients Approved</span>
                    <div class="stat-value text-indigo-700">{{ number_format($totalRecipientsApproved) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="col-12">
        <div class="card">
            <div class="card-body p-2.5 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <!-- Status Tabs -->
                <div class="d-flex p-1 bg-zinc-100 rounded-2 flex-wrap" role="group" id="filter-tabs-group">
                    <button type="button" class="filter-tab-btn active" id="tab-all" onclick="setFilter('all')">
                        All <span class="badge bg-secondary-soft text-zinc-600 ms-1">{{ $totalCount }}</span>
                    </button>
                    <button type="button" class="filter-tab-btn" id="tab-pending_approval" onclick="setFilter('pending_approval')">
                        <i class="bi bi-clock-history text-amber-600"></i> Pending Approval
                        <span class="badge bg-warning-soft text-amber-900 ms-1">{{ $pendingCount }}</span>
                    </button>
                    <button type="button" class="filter-tab-btn" id="tab-sent" onclick="setFilter('sent')">
                        <i class="bi bi-send text-emerald-600"></i> Sent & Dispatched
                        <span class="badge bg-success-soft text-emerald-900 ms-1">{{ $sentCount }}</span>
                    </button>
                    @if($scheduledCount > 0)
                    <button type="button" class="filter-tab-btn" id="tab-scheduled" onclick="setFilter('scheduled')">
                        <i class="bi bi-calendar text-primary"></i> Scheduled
                        <span class="badge bg-primary-soft text-indigo-700 ms-1">{{ $scheduledCount }}</span>
                    </button>
                    @endif
                    @if($rejectedCount > 0)
                    <button type="button" class="filter-tab-btn" id="tab-rejected" onclick="setFilter('rejected')">
                        <i class="bi bi-x-circle text-danger"></i> Rejected
                        <span class="badge bg-danger-soft text-rose-700 ms-1">{{ $rejectedCount }}</span>
                    </button>
                    @endif
                </div>

                <!-- Real-time Search Box -->
                <div class="d-flex align-items-center gap-2" style="min-width: 260px;">
                    <div class="input-group input-group-sm w-100">
                        <span class="input-group-text bg-white border-end-0 text-zinc-400"><i class="bi bi-search"></i></span>
                        <input type="text" id="campaign-search" class="form-control border-start-0 ps-1" placeholder="Search campaign subject or ID..." oninput="handleSearch(this.value)">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaigns Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center py-3">
                <span class="fw-semibold text-zinc-900">
                    <i class="bi bi-list-check me-1.5 text-zinc-500"></i> Campaign Records
                </span>
                <span class="text-zinc-500" style="font-size: 0.75rem;" id="results-count-summary">
                    Showing {{ $totalCount }} campaigns
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive border-0">
                    <table class="table align-middle mb-0" id="campaigns-table">
                        <thead class="bg-zinc-50 text-zinc-600" style="font-size: 0.75rem;">
                            <tr>
                                <th class="ps-4 py-3">Subject & Campaign ID</th>
                                <th>Status</th>
                                <th>Recipients</th>
                                <th>Sending Address</th>
                                <th>Submitted Date</th>
                                <th>Manager Authorization</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody id="campaigns-table-body">
                            @forelse($campaigns as $c)
                            @php
                                $isPending = in_array($c->status, ['pending_approval', 'pending_line_manager', 'pending_manager']);
                                $isSent = in_array($c->status, ['queued', 'sending', 'completed']);
                                $isScheduled = ($c->status === 'scheduled');
                                $isRejected = ($c->status === 'rejected');
                                $category = $isPending ? 'pending_approval' : ($isSent ? 'sent' : ($isScheduled ? 'scheduled' : ($isRejected ? 'rejected' : 'other')));
                            @endphp
                            <tr class="campaign-row" data-category="{{ $category }}" data-status="{{ $c->status }}" data-subject="{{ strtolower($c->subject) }}" data-id="{{ $c->id }}">
                                <td class="ps-4 py-3">
                                    <div class="fw-semibold text-zinc-900">
                                        <a href="{{ route('campaign_detail_view', $c->id) }}" class="text-decoration-none text-zinc-900 hover:text-indigo-600">
                                            {{ $c->subject ?: '(No Subject)' }}
                                        </a>
                                    </div>
                                    <div class="text-zinc-400 font-monospace" style="font-size: 0.7rem;">
                                        ID: {{ $c->id }}
                                    </div>
                                    @if($c->scheduled_at)
                                    <div class="text-indigo-600 font-monospace mt-0.5" style="font-size: 0.7rem;">
                                        <i class="bi bi-clock me-1"></i> Sched: {{ $c->scheduled_at->format('M d, Y h:i A') }}
                                    </div>
                                    @endif
                                </td>
                                <td>
                                    @if($c->status === 'pending_line_manager')
                                        <span class="badge bg-warning-soft text-amber-900"><i class="bi bi-clock me-1"></i>Pending Line Manager</span>
                                    @elseif($c->status === 'pending_manager')
                                        <span class="badge bg-warning-soft text-amber-900"><i class="bi bi-clock me-1"></i>Pending Manager</span>
                                    @elseif($c->status === 'pending_approval')
                                        <span class="badge bg-warning-soft text-amber-900"><i class="bi bi-clock me-1"></i>Pending Approval</span>
                                    @elseif($c->status === 'queued')
                                        <span class="badge bg-primary-soft text-indigo-700"><i class="bi bi-send me-1"></i>Queued</span>
                                    @elseif($c->status === 'sending')
                                        <span class="badge bg-primary-soft text-indigo-700"><i class="bi bi-arrow-repeat me-1"></i>Sending</span>
                                    @elseif($c->status === 'completed')
                                        <span class="badge bg-success-soft text-emerald-800"><i class="bi bi-check2-circle me-1"></i>Dispatched</span>
                                    @elseif($c->status === 'scheduled')
                                        <span class="badge bg-primary-soft"><i class="bi bi-calendar me-1"></i>Scheduled</span>
                                    @elseif($c->status === 'rejected')
                                        <span class="badge bg-danger-soft text-rose-700"><i class="bi bi-x-circle me-1"></i>Rejected</span>
                                    @else
                                        <span class="badge bg-secondary-soft text-zinc-700">{{ $c->status }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="font-monospace" style="font-size: 0.76rem;">
                                        <span class="text-zinc-900 fw-semibold">{{ $c->total_requested }}</span> req
                                        &bull; <span class="text-emerald-700">{{ $c->total_approved }} app</span>
                                        @if($c->total_blocked > 0)
                                        &bull; <span class="text-rose-700">{{ $c->total_blocked }} blk</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="text-zinc-800" style="font-size: 0.76rem;">
                                        {{ $c->from_address ?: 'rma@proitbuyer.com' }}
                                    </div>
                                    <div class="text-zinc-400 font-monospace" style="font-size: 0.7rem;">
                                        Domain: {{ $c->sending_domain ?: 'proitbuyer.com' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="text-zinc-700" style="font-size: 0.76rem;">
                                        {{ $c->created_at->format('M d, Y') }}
                                    </div>
                                    <div class="text-zinc-400" style="font-size: 0.7rem;">
                                        {{ $c->created_at->format('h:i A') }}
                                    </div>
                                </td>
                                <td>
                                    @if($c->status === 'pending_line_manager')
                                        <div class="text-amber-800 fw-medium d-flex align-items-center gap-1" style="font-size: 0.76rem;">
                                            <i class="bi bi-hourglass-split"></i> Awaiting Line Manager
                                        </div>
                                        @if($c->currentApprover)
                                            <div class="text-zinc-500" style="font-size: 0.7rem;">
                                                Approver: {{ $c->currentApprover->name ?: $c->currentApprover->username }}
                                            </div>
                                        @endif
                                    @elseif($c->status === 'pending_manager')
                                        <div class="text-amber-800 fw-medium d-flex align-items-center gap-1" style="font-size: 0.76rem;">
                                            <i class="bi bi-hourglass-split"></i> Awaiting Manager
                                        </div>
                                        @if($c->currentApprover)
                                            <div class="text-zinc-500" style="font-size: 0.7rem;">
                                                Approver: {{ $c->currentApprover->name ?: $c->currentApprover->username }}
                                            </div>
                                        @endif
                                    @elseif($c->status === 'pending_approval')
                                        <div class="text-amber-800 fw-medium d-flex align-items-center gap-1" style="font-size: 0.76rem;">
                                            <i class="bi bi-hourglass-split"></i> Awaiting Review
                                        </div>
                                    @elseif($c->status === 'rejected')
                                        <div class="text-rose-700 fw-medium" style="font-size: 0.76rem;">
                                            <i class="bi bi-x-circle me-1"></i> Rejected
                                            @if($c->rejecter)
                                                by {{ $c->rejecter->name ?: $c->rejecter->username }}
                                            @elseif($c->approver)
                                                by {{ $c->approver->name ?: $c->approver->username }}
                                            @endif
                                        </div>
                                        @php
                                            $rejectionComment = $c->rejection_reason ?: $c->approval_remark;
                                        @endphp
                                        @if($rejectionComment)
                                            <div class="text-zinc-500 text-truncate" style="font-size: 0.7rem; max-width: 180px;" title="{{ $rejectionComment }}">
                                                "{{ $rejectionComment }}"
                                            </div>
                                        @endif
                                    @elseif($c->approved_by || in_array($c->status, ['queued', 'sending', 'completed']))
                                        <div class="text-emerald-700 fw-medium" style="font-size: 0.76rem;">
                                            <i class="bi bi-check-circle me-1"></i> Authorized
                                            @if($c->approver)
                                                by {{ $c->approver->name ?: $c->approver->username }}
                                            @endif
                                        </div>
                                        @if($c->approval_at)
                                            <div class="text-zinc-400 font-monospace" style="font-size: 0.68rem;">
                                                {{ $c->approval_at->format('M d, h:i A') }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-zinc-400" style="font-size: 0.75rem;">-</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('campaign_detail_view', $c->id) }}" class="btn btn-outline-secondary btn-xs">
                                        <i class="bi bi-eye"></i> View Telemetry
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr id="no-campaigns-row">
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block text-zinc-300 mb-2"></i>
                                    <div class="fw-semibold text-zinc-700">No campaigns created yet</div>
                                    <p class="text-zinc-400 mb-3" style="font-size: 0.8rem;">You have not created any email campaigns under your account.</p>
                                    <a href="{{ route('campaign_create_view') }}" class="btn btn-primary btn-sm">
                                        <i class="bi bi-plus-lg"></i> Create First Campaign
                                    </a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('extra_scripts')
<script>
    let currentFilter = 'all';
    let searchQuery = '';

    function setFilter(filter) {
        currentFilter = filter;
        document.querySelectorAll('.filter-tab-btn').forEach(b => b.classList.remove('active'));
        const activeBtn = document.getElementById(`tab-${filter}`);
        if (activeBtn) activeBtn.classList.add('active');
        applyFilters();
    }

    function handleSearch(val) {
        searchQuery = (val || '').trim().toLowerCase();
        applyFilters();
    }

    function applyFilters() {
        const rows = document.querySelectorAll('.campaign-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const cat = row.getAttribute('data-category');
            const subj = row.getAttribute('data-subject') || '';
            const id = row.getAttribute('data-id') || '';

            let matchesFilter = (currentFilter === 'all') || (cat === currentFilter);
            let matchesSearch = !searchQuery || subj.includes(searchQuery) || id.includes(searchQuery);

            if (matchesFilter && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const summary = document.getElementById('results-count-summary');
        if (summary) {
            summary.innerText = `Showing ${visibleCount} campaigns`;
        }
    }
</script>
@endsection