@extends('layouts.app')

@section('page_title', 'Salesforce Custom Users (SF_User__c)')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h4 fw-bold text-zinc-900 mb-0">Salesforce Custom Users (SF_User__c)</h1>
                <span class="badge bg-primary-soft">
                    <i class="bi bi-person-badge me-1"></i> {{ number_format($totalLocalSfUsers) }} Synced in MySQL
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
                Automated background import from Salesforce custom object <code>SF_User__c</code> (Employee Profiles & Prime Owners) into local database.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Manual Sync Form -->
            <form action="{{ route('admin.salesforce_sf_users.sync') }}" method="POST" id="manual-sync-sf-users-form" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm shadow-sm d-flex align-items-center gap-1.5" id="sync-sf-users-btn" onclick="this.disabled=true; this.innerHTML='<span class=\"spinner-border spinner-border-sm me-1\"></span> Syncing...'; this.form.submit();">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Sync SF Users Now</span>
                </button>
            </form>

            <!-- Full Sync Option Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-gear"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.8125rem;">
                    <li>
                        <form action="{{ route('admin.salesforce_sf_users.sync') }}" method="POST">
                            @csrf
                            <input type="hidden" name="full" value="1">
                            <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2" onclick="return confirm('Force full sync will scan all available SF_User__c records. Proceed?');">
                                <i class="bi bi-arrow-clockwise"></i> Force Full Resync
                            </button>
                        </form>
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
            <form method="GET" action="{{ route('admin.salesforce_sf_users.index') }}" class="row g-2 align-items-center">
                <!-- Search Keyword -->
                <div class="col-12 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white text-muted border-end-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search name, emp code, email, process..." value="{{ $search }}">
                    </div>
                </div>

                <!-- Active Status Filter -->
                <div class="col-6 col-md-2">
                    <select name="is_active" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="1" {{ $selectedActive === '1' ? 'selected' : '' }}>Active Only</option>
                        <option value="0" {{ $selectedActive === '0' ? 'selected' : '' }}>Inactive Only</option>
                    </select>
                </div>

                <!-- Process / Department Filter -->
                <div class="col-6 col-md-2">
                    <select name="process" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Processes</option>
                        @foreach($processes as $pr)
                            <option value="{{ $pr }}" {{ $selectedProcess === $pr ? 'selected' : '' }}>{{ $pr }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Team Filter -->
                <div class="col-6 col-md-2">
                    <select name="team" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Teams</option>
                        @foreach($teams as $tm)
                            <option value="{{ $tm }}" {{ $selectedTeam === $tm ? 'selected' : '' }}>{{ $tm }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Records per Page -->
                <div class="col-6 col-md-1">
                    <select name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                        <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="col-12 col-md-2 d-flex gap-1.5 justify-content-end">
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-50">Filter</button>
                    <a href="{{ route('admin.salesforce_sf_users.index') }}" class="btn btn-light btn-sm w-50 border text-secondary" title="Reset Filters">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Main SF Users Table Card -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold text-zinc-900" style="font-size: 0.875rem;">
                <i class="bi bi-person-workspace text-primary me-1.5"></i> SF Custom Users (SF_User__c) Directory
            </span>
            <span class="small text-secondary">
                Showing {{ $sfUsers->firstItem() ?? 0 }} to {{ $sfUsers->lastItem() ?? 0 }} of {{ number_format($sfUsers->total()) }} records
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4" style="min-width: 180px;">Name (Short / US / IN)</th>
                            <th style="min-width: 170px;">Emp Email / Code</th>
                            <th style="min-width: 130px;">Process & Team</th>
                            <th style="min-width: 110px;">Location</th>
                            <th style="min-width: 100px;">Status</th>
                            <th style="min-width: 130px;">Dates (DOJ / DOL)</th>
                            <th style="min-width: 180px;">Prime Owned Records</th>
                            <th style="min-width: 130px;">Standard SF User</th>
                            <th style="min-width: 140px;">SF Modified</th>
                            <th class="pe-4 text-end" style="min-width: 140px;">Local Synced</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sfUsers as $u)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center text-primary fw-bold" style="width: 32px; height: 32px; font-size: 0.75rem; flex-shrink: 0;">
                                        {{ strtoupper(substr($u->name ?: $u->emp_name ?: 'S', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-zinc-900">{{ $u->name ?: $u->emp_name ?: 'Unnamed SF User' }}</div>
                                        @if($u->full_name_in && $u->full_name_in !== $u->name)
                                            <div class="text-secondary small">{{ $u->full_name_in }}</div>
                                        @elseif($u->emp_name && $u->emp_name !== $u->name)
                                            <div class="text-secondary small">{{ $u->emp_name }}</div>
                                        @endif
                                        <div class="text-muted font-monospace" style="font-size: 0.7rem;">{{ $u->salesforce_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($u->emp_email)
                                    <a href="mailto:{{ $u->emp_email }}" class="text-decoration-none text-zinc-900 fw-medium d-inline-flex align-items-center gap-1 text-truncate" style="max-width: 180px;" title="{{ $u->emp_email }}">
                                        <i class="bi bi-envelope text-zinc-400"></i> {{ $u->emp_email }}
                                    </a>
                                @endif
                                @if($u->emp_code)
                                    <div><span class="badge bg-secondary-soft text-zinc-700" style="font-size: 0.68rem;">Code: {{ $u->emp_code }}</span></div>
                                @endif
                            </td>
                            <td>
                                <div class="text-zinc-800 small fw-medium">{{ $u->process ?: '-' }}</div>
                                @if($u->team)
                                    <div class="text-muted small" style="font-size: 0.72rem;">Team: {{ $u->team }}</div>
                                @endif
                            </td>
                            <td>
                                @if($u->location)
                                    <span class="badge bg-secondary-soft text-zinc-800">{{ $u->location }}</span>
                                @else
                                    <span class="text-zinc-400 fst-italic">-</span>
                                @endif
                            </td>
                            <td>
                                @if($u->is_active)
                                    <span class="badge bg-success-soft"><i class="bi bi-check-circle me-1"></i>Active</span>
                                @else
                                    <span class="badge bg-secondary-soft text-muted"><i class="bi bi-dash-circle me-1"></i>Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="small text-zinc-800">
                                    @if($u->doj)
                                        <div><span class="text-muted" style="font-size: 0.7rem;">DOJ:</span> {{ $u->doj->format('M d, Y') }}</div>
                                    @endif
                                    @if($u->dol)
                                        <div class="text-danger"><span class="text-muted" style="font-size: 0.7rem;">DOL:</span> {{ $u->dol->format('M d, Y') }}</div>
                                    @endif
                                    @if(!$u->doj && !$u->dol)
                                        <span class="text-zinc-400 fst-italic">-</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="{{ route('admin.salesforce_leads.index', ['prime_owner_id' => $u->salesforce_id]) }}" class="badge bg-primary-soft text-decoration-none" title="Prime Owned Leads">
                                        <i class="bi bi-funnel me-0.5"></i> {{ $u->prime_leads_count }} Leads
                                    </a>
                                    <a href="{{ route('admin.salesforce_accounts.index', ['search' => $u->name]) }}" class="badge bg-primary-soft text-decoration-none" title="Prime Owned Accounts">
                                        <i class="bi bi-buildings me-0.5"></i> {{ $u->prime_accounts_count }} Accs
                                    </a>
                                    <a href="{{ route('admin.salesforce_contacts.index', ['search' => $u->name]) }}" class="badge bg-primary-soft text-decoration-none" title="Prime Owned Contacts">
                                        <i class="bi bi-person me-0.5"></i> {{ $u->prime_contacts_count }} Cons
                                    </a>
                                </div>
                            </td>
                            <td>
                                <div class="text-secondary small" title="{{ $u->owner_id }}">
                                    {{ $u->standardUser ? $u->standardUser->name : ($u->owner_id ?: '-') }}
                                </div>
                            </td>
                            <td>
                                <span class="text-secondary small text-nowrap">
                                    {{ $u->salesforce_updated_at ? $u->salesforce_updated_at->format('M d, Y h:i A') : '-' }}
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <span class="text-secondary small text-nowrap" title="{{ $u->synced_at }}">
                                    {{ $u->synced_at ? $u->synced_at->diffForHumans() : '-' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 48px; height: 48px;">
                                    <i class="bi bi-person-badge text-muted fs-4"></i>
                                </div>
                                <h6 class="fw-semibold text-zinc-800 mb-1">No Synchronized SF Custom Users Found</h6>
                                <p class="text-secondary small mb-3">
                                    @if($search || $selectedActive !== null || $selectedProcess || $selectedTeam)
                                        No SF Users matched the search criteria. Try clearing your filters.
                                    @else
                                        Click "Sync SF Users Now" or wait for the scheduled cron job to import SF_User__c records from Salesforce.
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
                Showing {{ $sfUsers->firstItem() ?? 0 }} to {{ $sfUsers->lastItem() ?? 0 }} of {{ number_format($sfUsers->total()) }} records
            </div>
            <div>
                {{ $sfUsers->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection
