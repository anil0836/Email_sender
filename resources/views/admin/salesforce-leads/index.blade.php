@extends('layouts.app')

@section('page_title', 'Salesforce Synchronized Leads')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h4 fw-bold text-zinc-900 mb-0">Salesforce Synchronized Leads</h1>
                <span class="badge bg-primary-soft">
                    <i class="bi bi-database-check me-1"></i> {{ number_format($totalLocalLeads) }} Synced in MySQL
                </span>
                @if($latestSync)
                    @if($latestSync->status === 'success')
                        <span class="badge bg-success-soft" title="Last sync completed at {{ $latestSync->completed_at ? $latestSync->completed_at->format('M d, Y h:i A') : '-' }}">
                            <i class="bi bi-check-circle me-1"></i> Synced {{ $latestSync->completed_at ? $latestSync->completed_at->diffForHumans() : 'Recently' }}
                        </span>
                    @elseif($latestSync->status === 'running')
                        <span class="badge bg-warning-soft">
                            <span class="spinner-border spinner-border-sm me-1" role="status" style="width: 0.6rem; height: 0.6rem;"></span> Syncing in Background...
                        </span>
                    @else
                        <span class="badge bg-danger-soft" title="{{ $latestSync->error_message }}">
                            <i class="bi bi-exclamation-triangle me-1"></i> Sync Issue
                        </span>
                    @endif
                @endif
            </div>
            <p class="text-secondary small mb-0">
                Automated background import from Salesforce Lead object into local database via Laravel Scheduler & Cron.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Sync Today Form -->
            <form action="{{ route('admin.salesforce_leads.sync') }}" method="POST" id="today-sync-form" class="d-inline">
                @csrf
                <input type="hidden" name="today" value="1">
                <button type="submit" class="btn btn-primary btn-sm shadow-sm d-flex align-items-center gap-1.5" id="sync-today-btn" onclick="this.disabled=true; this.innerHTML='<span class=\"spinner-border spinner-border-sm me-1\"></span> Syncing Today...'; this.form.submit();">
                    <i class="bi bi-calendar-check"></i>
                    <span>Sync Today's Leads</span>
                </button>
            </form>

            <!-- Sync Latest Incremental Form -->
            <form action="{{ route('admin.salesforce_leads.sync') }}" method="POST" id="manual-sync-form" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm shadow-sm d-flex align-items-center gap-1.5" id="sync-now-btn" onclick="this.disabled=true; this.innerHTML='<span class=\"spinner-border spinner-border-sm me-1\"></span> Syncing...'; this.form.submit();">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Sync Latest Leads</span>
                </button>
            </form>

            <!-- Full Sync Option Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Sync options">
                    <i class="bi bi-gear"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.8125rem;">
                    <li>
                        <form action="{{ route('admin.salesforce_leads.sync') }}" method="POST">
                            @csrf
                            <input type="hidden" name="today" value="1">
                            <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                                <i class="bi bi-calendar-event"></i> Sync Today's Leads Only
                            </button>
                        </form>
                    </li>
                    <li>
                        <form action="{{ route('admin.salesforce_leads.sync') }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                                <i class="bi bi-clock-history"></i> Sync Latest (Incremental)
                            </button>
                        </form>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="{{ route('admin.salesforce_leads.sync') }}" method="POST">
                            @csrf
                            <input type="hidden" name="full" value="1">
                            <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2" onclick="return confirm('Force full sync will scan all available Salesforce records (starting from latest down to oldest). Proceed?');">
                                <i class="bi bi-arrow-clockwise"></i> Force Full Resync (Latest to Oldest)
                            </button>
                        </form>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('salesforce.leads') }}">
                            <i class="bi bi-lightning-charge"></i> Live Direct Salesforce Viewer
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Sync Status Mini-Banner -->
    @if($latestSync)
    <div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
        <div class="card-body py-2.5 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2 text-secondary small">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span>
                    <strong class="text-zinc-800">Last Sync:</strong> 
                    {{ $latestSync->completed_at ? $latestSync->completed_at->format('M d, Y h:i:s A') : 'In progress...' }}
                </span>
                <span class="text-zinc-300">&bull;</span>
                <span>
                    <strong class="text-zinc-800">Type:</strong> 
                    <span class="text-capitalize">{{ $latestSync->sync_type }}</span>
                </span>
                <span class="text-zinc-300">&bull;</span>
                <span>
                    <strong class="text-zinc-800">Fetched:</strong> {{ number_format($latestSync->records_fetched) }}
                </span>
                <span class="text-zinc-300">&bull;</span>
                <span>
                    <strong class="text-success">{{ number_format($latestSync->records_created) }} created</strong>, 
                    <strong class="text-primary">{{ number_format($latestSync->records_updated) }} updated</strong>
                </span>
            </div>
            @if($latestSync->last_modified_checkpoint)
            <div class="text-muted font-monospace" style="font-size: 0.72rem;">
                Checkpoint: {{ $latestSync->last_modified_checkpoint->format('Y-m-d H:i:s') }}
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Search & Filter Card -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('admin.salesforce_leads.index') }}" class="row g-2">
                <!-- Search Keyword -->
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1">Search Leads</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white text-muted border-end-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Name, company, email, SF ID..." value="{{ $search }}">
                    </div>
                </div>

                <!-- Owner Verification Filter -->
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1"><i class="bi bi-shield-check text-success me-1"></i>Owner Verification</label>
                    <select name="verification_status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Verification States</option>
                        <option value="verified" {{ ($selectedVerificationStatus ?? '') === 'verified' ? 'selected' : '' }}>Verified</option>
                        <option value="changed" {{ ($selectedVerificationStatus ?? '') === 'changed' ? 'selected' : '' }}>Changed (Reassigned)</option>
                        <option value="unverified" {{ ($selectedVerificationStatus ?? '') === 'unverified' ? 'selected' : '' }}>Unverified</option>
                    </select>
                </div>

                <!-- Prime Owner (SF_User__c) Filter -->
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1"><i class="bi bi-person-badge text-primary me-1"></i>Prime Owner (SF User)</label>
                    <select name="prime_owner_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Prime Owners</option>
                        @foreach($sfUsers as $u)
                            <option value="{{ $u->salesforce_id }}" {{ $selectedPrimeOwner === $u->salesforce_id ? 'selected' : '' }}>
                                {{ $u->name }} {{ $u->emp_name && $u->emp_name !== $u->name ? '('.$u->emp_name.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Standard Owner (User) Filter -->
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1"><i class="bi bi-person text-secondary me-1"></i>Standard Owner (User)</label>
                    <select name="owner_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Standard Users</option>
                        @foreach($standardUsers as $su)
                            <option value="{{ $su->salesforce_id }}" {{ $selectedOwner === $su->salesforce_id ? 'selected' : '' }}>
                                {{ $su->name }} ({{ $su->username }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $st)
                            <option value="{{ $st }}" {{ $selectedStatus === $st ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Lead Source Filter -->
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Lead Source</label>
                    <select name="lead_source" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Lead Sources</option>
                        @foreach($leadSources as $src)
                            <option value="{{ $src }}" {{ $selectedSource === $src ? 'selected' : '' }}>{{ $src }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Sort By -->
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Sort By</label>
                    <select name="sort_by" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="salesforce_updated_at" {{ ($sortBy ?? '') === 'salesforce_updated_at' ? 'selected' : '' }}>SF Updated Time</option>
                        <option value="salesforce_created_at" {{ ($sortBy ?? '') === 'salesforce_created_at' ? 'selected' : '' }}>SF Created Time</option>
                        <option value="name" {{ ($sortBy ?? '') === 'name' ? 'selected' : '' }}>Lead Name</option>
                        <option value="company" {{ ($sortBy ?? '') === 'company' ? 'selected' : '' }}>Company</option>
                        <option value="owner_verification_status" {{ ($sortBy ?? '') === 'owner_verification_status' ? 'selected' : '' }}>Verification Status</option>
                        <option value="last_owner_verified_at" {{ ($sortBy ?? '') === 'last_owner_verified_at' ? 'selected' : '' }}>Last Verified Date</option>
                    </select>
                </div>

                <!-- Records per Page & Actions -->
                <div class="col-6 col-md-3 d-flex align-items-end gap-1.5">
                    <select name="per_page" class="form-select form-select-sm" style="max-width: 100px;" onchange="this.form.submit()">
                        <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10 / page</option>
                        <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25 / page</option>
                        <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 / page</option>
                        <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 / page</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">Apply</button>
                    <a href="{{ route('admin.salesforce_leads.index') }}" class="btn btn-light btn-sm border text-secondary" title="Reset Filters"><i class="bi bi-x"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Leads Table Card -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold text-zinc-900" style="font-size: 0.875rem;">
                <i class="bi bi-person-lines-fill text-primary me-1.5"></i> Synchronized Leads Directory
            </span>
            <span class="small text-secondary">
                Showing {{ $leads->firstItem() ?? 0 }} to {{ $leads->lastItem() ?? 0 }} of {{ number_format($leads->total()) }} records
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4" style="min-width: 180px;">Lead Name</th>
                            <th style="min-width: 150px;">Company</th>
                            <th style="min-width: 170px;">Email</th>
                            <th style="min-width: 120px;">Phone</th>
                            <th style="min-width: 100px;">Status</th>
                            <th style="min-width: 140px;">Owner Verification</th>
                            <th style="min-width: 130px;">Salesforce Owner</th>
                            <th style="min-width: 130px;">Custom Owner</th>
                            <th style="min-width: 140px;">Local SF User</th>
                            <th style="min-width: 130px;">SF Modified</th>
                            <th class="pe-4 text-end" style="min-width: 100px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leads as $lead)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center text-primary fw-bold" style="width: 32px; height: 32px; font-size: 0.75rem; flex-shrink: 0;">
                                        {{ strtoupper(substr($lead->name ?: $lead->last_name ?: 'L', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-zinc-900">{{ $lead->name ?: trim($lead->first_name . ' ' . $lead->last_name) ?: 'Unnamed Lead' }}</div>
                                        <div class="text-secondary small text-truncate" style="max-width: 180px;" title="{{ $lead->title }}">
                                            {{ $lead->title ?: 'No title' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-medium text-zinc-800 text-truncate" style="max-width: 150px;" title="{{ $lead->company }}">
                                    {{ $lead->company ?: '-' }}
                                </div>
                                @if($lead->industry)
                                <div class="text-muted small" style="font-size: 0.72rem;">{{ $lead->industry }}</div>
                                @endif
                            </td>
                            <td>
                                @if($lead->email)
                                    <a href="mailto:{{ $lead->email }}" class="text-decoration-none text-zinc-900 fw-medium d-inline-flex align-items-center gap-1 text-truncate" style="max-width: 170px;" title="{{ $lead->email }}">
                                        <i class="bi bi-envelope text-zinc-400"></i> {{ $lead->email }}
                                    </a>
                                @else
                                    <span class="text-zinc-400 fst-italic">-</span>
                                @endif
                            </td>
                            <td>
                                @if($lead->phone || $lead->mobile_phone)
                                    <span class="text-zinc-800 d-inline-flex align-items-center gap-1 font-monospace" style="font-size: 0.8rem;">
                                        <i class="bi bi-telephone text-zinc-400"></i> {{ $lead->phone ?: $lead->mobile_phone }}
                                    </span>
                                @else
                                    <span class="text-zinc-400 fst-italic">-</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $st = strtolower($lead->status ?? '');
                                @endphp
                                @if(str_contains($st, 'qualified') || str_contains($st, 'converted'))
                                    <span class="badge bg-success-soft"><i class="bi bi-check-circle me-1"></i>{{ $lead->status }}</span>
                                @elseif(str_contains($st, 'working') || str_contains($st, 'contacted'))
                                    <span class="badge bg-primary-soft"><i class="bi bi-clock-history me-1"></i>{{ $lead->status }}</span>
                                @elseif(str_contains($st, 'open') || str_contains($st, 'new'))
                                    <span class="badge bg-warning-soft"><i class="bi bi-sun me-1"></i>{{ $lead->status }}</span>
                                @elseif(str_contains($st, 'unqualified') || str_contains($st, 'lost'))
                                    <span class="badge bg-danger-soft"><i class="bi bi-x-circle me-1"></i>{{ $lead->status }}</span>
                                @else
                                    <span class="badge bg-secondary-soft">{{ $lead->status ?: 'Unknown' }}</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $vStatus = $lead->owner_verification_status ?? 'verified';
                                @endphp
                                @if($vStatus === 'verified')
                                    <span class="badge bg-success-soft" title="Verified at {{ $lead->last_owner_verified_at ? $lead->last_owner_verified_at->format('M d, Y H:i') : 'Import' }}">
                                        <i class="bi bi-shield-check me-1"></i> Verified
                                    </span>
                                @elseif($vStatus === 'changed')
                                    <span class="badge bg-warning-soft text-amber-800" title="Owner changed from {{ $lead->previous_owner_id }} on {{ $lead->last_owner_verified_at ? $lead->last_owner_verified_at->format('M d, Y H:i') : 'Sync' }}">
                                        <i class="bi bi-arrow-repeat me-1"></i> Changed
                                    </span>
                                @else
                                    <span class="badge bg-secondary-soft">
                                        <i class="bi bi-question-circle me-1"></i> Unverified
                                    </span>
                                @endif
                                @if($lead->last_owner_verified_at)
                                    <div class="text-muted" style="font-size: 0.68rem;">{{ $lead->last_owner_verified_at->diffForHumans() }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="text-zinc-900 small fw-medium" title="Owner ID: {{ $lead->owner_id }}">
                                    {{ $lead->owner_name ?: ($lead->owner ? $lead->owner->name : ($lead->owner_id ?: '-')) }}
                                </div>
                                @if($lead->owner_email)
                                    <div class="text-muted small" style="font-size: 0.72rem;">{{ $lead->owner_email }}</div>
                                @endif
                            </td>
                            <td>
                                @if($lead->custom_owner)
                                    <span class="badge bg-light border text-zinc-900 fw-medium" title="Custom_Owner__c: {{ $lead->custom_owner }}">
                                        <i class="bi bi-person-gear text-primary me-1"></i>{{ $lead->custom_owner }}
                                    </span>
                                @else
                                    <span class="text-zinc-400 fst-italic">-</span>
                                @endif
                            </td>
                            <td>
                                @if($lead->salesforceOwner)
                                    <a href="{{ route('admin.salesforce_sf_users.index', ['search' => $lead->salesforceOwner->name]) }}" class="text-decoration-none fw-medium text-zinc-900 d-inline-flex align-items-center gap-1" title="Matched SF User: {{ $lead->salesforceOwner->name }}">
                                        <i class="bi bi-person-check-fill text-success"></i> {{ $lead->salesforceOwner->name }}
                                    </a>
                                @elseif($lead->primeOwner)
                                    <a href="{{ route('admin.salesforce_sf_users.index', ['search' => $lead->primeOwner->name]) }}" class="text-decoration-none fw-medium text-zinc-900 d-inline-flex align-items-center gap-1" title="Prime Owner: {{ $lead->primeOwner->name }}">
                                        <i class="bi bi-person-badge text-primary"></i> {{ $lead->primeOwner->name }}
                                    </a>
                                @elseif($lead->prime_owner_id)
                                    <code class="text-muted small">{{ $lead->prime_owner_id }}</code>
                                @else
                                    <span class="text-zinc-400 fst-italic">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-secondary small text-nowrap">
                                    {{ $lead->salesforce_updated_at ? $lead->salesforce_updated_at->format('M d, Y h:i A') : '-' }}
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <button type="button" class="btn btn-outline-primary btn-xs" onclick="openLeadModal('{{ $lead->id }}')">
                                    <i class="bi bi-eye me-1"></i> Details
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center py-5">
                                <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 48px; height: 48px;">
                                    <i class="bi bi-inbox text-muted fs-4"></i>
                                </div>
                                <h6 class="fw-semibold text-zinc-800 mb-1">No Synchronized Leads Found</h6>
                                <p class="text-secondary small mb-3">
                                    @if($search || $selectedStatus || $selectedSource || ($selectedVerificationStatus ?? null))
                                        No leads matched the search criteria. Try clearing your filters.
                                    @else
                                        Click "Sync Leads Now" or wait for the scheduled cron job to import leads from Salesforce.
                                    @endif
                                </p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Footer -->
        <div class="card-footer bg-white border-top py-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
            <div class="small text-secondary">
                Showing {{ $leads->firstItem() ?? 0 }} to {{ $leads->lastItem() ?? 0 }} of {{ number_format($leads->total()) }} leads
            </div>
            <div>
                {{ $leads->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>

<!-- Lead Detail Inspection Modal -->
<div class="modal fade" id="leadDetailModal" tabindex="-1" aria-labelledby="leadDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3">
                <div>
                    <h5 class="modal-title fw-bold text-zinc-900" id="leadDetailModalLabel">Lead Details & Owner Verification</h5>
                    <div class="text-muted small" id="modal-lead-sfid">Loading...</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="modal-lead-body">
                <div class="text-center py-5">
                    <span class="spinner-border spinner-border-sm text-primary me-2"></span> Loading lead data...
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openLeadModal(leadId) {
    const modalEl = document.getElementById('leadDetailModal');
    const modal = new bootstrap.Modal(modalEl);
    const bodyEl = document.getElementById('modal-lead-body');
    const titleSfid = document.getElementById('modal-lead-sfid');

    bodyEl.innerHTML = '<div class="text-center py-5"><span class="spinner-border spinner-border-sm text-primary me-2"></span> Loading lead details...</div>';
    modal.show();

    fetch(`/admin/salesforce-leads/${leadId}`, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        const lead = data.lead;
        const owner = data.owner;
        const primeOwner = data.prime_owner;
        const salesforceOwner = data.salesforce_owner;
        const campaigns = data.campaign_members || [];

        titleSfid.innerHTML = `<span class="badge bg-primary-soft me-2">SF ID: ${escapeHtml(lead.salesforce_id || '-')}</span> <span class="badge bg-secondary-soft">Local ID: #${lead.id}</span>`;

        let vBadge = '<span class="badge bg-success-soft"><i class="bi bi-shield-check me-1"></i> Verified</span>';
        if (lead.owner_verification_status === 'changed') {
            vBadge = '<span class="badge bg-warning-soft text-amber-800"><i class="bi bi-arrow-repeat me-1"></i> Changed (Reassigned)</span>';
        } else if (lead.owner_verification_status === 'unverified') {
            vBadge = '<span class="badge bg-secondary-soft"><i class="bi bi-question-circle me-1"></i> Unverified</span>';
        }

        let campaignsHtml = '';
        if (campaigns.length > 0) {
            campaignsHtml = `
                <div class="table-responsive mt-2">
                    <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 0.78rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Campaign</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Owner At Dispatch</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${campaigns.map(cm => `
                                <tr>
                                    <td class="fw-semibold">${escapeHtml(cm.campaign ? cm.campaign.name : cm.campaign_id)}</td>
                                    <td class="text-truncate" style="max-width: 150px;">${escapeHtml(cm.campaign ? cm.campaign.subject : '-')}</td>
                                    <td><span class="badge bg-primary-soft">${escapeHtml(cm.status || 'queued')}</span></td>
                                    <td>${escapeHtml(cm.owner_name || cm.salesforce_owner_id || '-')}</td>
                                    <td class="text-nowrap">${cm.created_at ? new Date(cm.created_at).toLocaleDateString() : '-'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        } else {
            campaignsHtml = '<div class="text-muted small py-2 fst-italic">This lead has not participated in any campaign dispatches yet.</div>';
        }

        bodyEl.innerHTML = `
            <!-- Owner Verification Summary Card -->
            <div class="card border-primary border-opacity-25 bg-primary bg-opacity-10 mb-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-zinc-900 mb-0"><i class="bi bi-shield-check text-primary me-1.5"></i> Salesforce Owner Verification</h6>
                        <div>${vBadge}</div>
                    </div>
                    <div class="row g-2 text-secondary small">
                        <div class="col-sm-6">
                            <span class="text-muted">Assigned Owner:</span> 
                            <strong class="text-zinc-900">${escapeHtml(lead.owner_name || (salesforceOwner ? salesforceOwner.name : (owner ? owner.name : (lead.owner_id || 'Unassigned'))))}</strong>
                            ${lead.owner_email ? `<div class="font-monospace text-muted" style="font-size: 0.75rem;">${escapeHtml(lead.owner_email)}</div>` : ''}
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Salesforce Owner ID:</span> 
                            <code class="text-zinc-800">${escapeHtml(lead.owner_id || '-')}</code>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Custom Owner (SF):</span> 
                            <strong class="text-zinc-900">${escapeHtml(lead.custom_owner || 'None')}</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Matched Local SF User:</span> 
                            <strong class="text-zinc-900 text-success">${escapeHtml(salesforceOwner ? salesforceOwner.name : (primeOwner ? primeOwner.name : 'Unmatched'))}</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Prime Owner (SF_User__c):</span> 
                            <strong class="text-zinc-900">${escapeHtml(primeOwner ? primeOwner.name : (lead.prime_owner_id || '-'))}</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Last Verified At:</span> 
                            <span class="text-zinc-900">${lead.last_owner_verified_at ? new Date(lead.last_owner_verified_at).toLocaleString() : '-'}</span>
                        </div>
                        ${lead.previous_owner_id ? `
                        <div class="col-12 text-warning fw-medium">
                            <i class="bi bi-exclamation-triangle me-1"></i> Previous Owner ID: <code>${escapeHtml(lead.previous_owner_id)}</code>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>

            <!-- Profile Info Grid -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card h-100 border-0 bg-light p-3">
                        <h6 class="fw-bold text-zinc-900 mb-2.5"><i class="bi bi-person me-1.5 text-secondary"></i> Lead Profile</h6>
                        <table class="table table-sm table-borderless mb-0 small">
                            <tr><td class="text-muted ps-0" style="width: 100px;">Full Name:</td><td class="fw-semibold text-zinc-900">${escapeHtml(lead.name || trim((lead.first_name || '') + ' ' + (lead.last_name || '')) || '-')}</td></tr>
                            <tr><td class="text-muted ps-0">Title:</td><td>${escapeHtml(lead.title || '-')}</td></tr>
                            <tr><td class="text-muted ps-0">Company:</td><td class="fw-medium">${escapeHtml(lead.company || '-')}</td></tr>
                            <tr><td class="text-muted ps-0">Email:</td><td>${lead.email ? `<a href="mailto:${escapeHtml(lead.email)}">${escapeHtml(lead.email)}</a>` : '-'}</td></tr>
                            <tr><td class="text-muted ps-0">Phone:</td><td>${escapeHtml(lead.phone || '-')}</td></tr>
                            <tr><td class="text-muted ps-0">Mobile:</td><td>${escapeHtml(lead.mobile_phone || '-')}</td></tr>
                            <tr><td class="text-muted ps-0">Status:</td><td><span class="badge bg-secondary-soft">${escapeHtml(lead.status || '-')}</span></td></tr>
                            <tr><td class="text-muted ps-0">Lead Source:</td><td>${escapeHtml(lead.lead_source || '-')}</td></tr>
                        </table>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card h-100 border-0 bg-light p-3">
                        <h6 class="fw-bold text-zinc-900 mb-2.5"><i class="bi bi-building me-1.5 text-secondary"></i> Business & Address</h6>
                        <table class="table table-sm table-borderless mb-0 small">
                            <tr><td class="text-muted ps-0" style="width: 100px;">Industry:</td><td>${escapeHtml(lead.industry || '-')}</td></tr>
                            <tr><td class="text-muted ps-0">Rating:</td><td>${escapeHtml(lead.rating || '-')}</td></tr>
                            <tr><td class="text-muted ps-0">Annual Rev:</td><td>${lead.annual_revenue ? '$' + Number(lead.annual_revenue).toLocaleString() : '-'}</td></tr>
                            <tr><td class="text-muted ps-0">Street:</td><td>${escapeHtml(lead.street || '-')}</td></tr>
                            <tr><td class="text-muted ps-0">City, State:</td><td>${escapeHtml([lead.city, lead.state].filter(Boolean).join(', ') || '-')}</td></tr>
                            <tr><td class="text-muted ps-0">Postal, Country:</td><td>${escapeHtml([lead.postal_code, lead.country].filter(Boolean).join(', ') || '-')}</td></tr>
                            <tr><td class="text-muted ps-0">SF Created:</td><td>${lead.salesforce_created_at ? new Date(lead.salesforce_created_at).toLocaleString() : '-'}</td></tr>
                            <tr><td class="text-muted ps-0">SF Modified:</td><td>${lead.salesforce_updated_at ? new Date(lead.salesforce_updated_at).toLocaleString() : '-'}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Campaign Participation -->
            <div class="mb-4">
                <h6 class="fw-bold text-zinc-900 mb-2"><i class="bi bi-envelope-paper me-1.5 text-primary"></i> Campaign Participation History</h6>
                ${campaignsHtml}
            </div>

            <!-- Raw Data Accordion -->
            <div class="accordion" id="rawAccordion">
                <div class="accordion-item border rounded-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed py-2 px-3 bg-white small fw-medium" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRaw" aria-expanded="false" aria-controls="collapseRaw">
                            <i class="bi bi-code-slash me-2 text-muted"></i> Raw Local & Salesforce Payload
                        </button>
                    </h2>
                    <div id="collapseRaw" class="accordion-collapse collapse" data-bs-parent="#rawAccordion">
                        <div class="accordion-body p-2 bg-dark">
                            <pre class="text-light mb-0 font-monospace" style="font-size: 0.72rem; max-height: 200px; overflow-y: auto;">${escapeHtml(JSON.stringify(lead.raw_data || lead, null, 2))}</pre>
                        </div>
                    </div>
                </div>
            </div>
        `;
    })
    .catch(err => {
        bodyEl.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Failed to load lead details: ${escapeHtml(err.message)}</div>`;
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>
@endsection
