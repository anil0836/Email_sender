@extends('layouts.app')

@section('page_title', 'Salesforce Synchronized Accounts')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h4 fw-bold text-zinc-900 mb-0">Salesforce Synchronized Accounts</h1>
                <span class="badge bg-primary-soft">
                    <i class="bi bi-building-check me-1"></i> {{ number_format($totalLocalAccounts) }} Synced in MySQL
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
                Automated background import from Salesforce Account object into local database via Laravel Scheduler & Cron.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Manual Sync Form -->
            <form action="{{ route('admin.salesforce_accounts.sync') }}" method="POST" id="manual-sync-accounts-form" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm shadow-sm d-flex align-items-center gap-1.5" id="sync-accounts-btn" onclick="this.disabled=true; this.innerHTML='<span class=\"spinner-border spinner-border-sm me-1\"></span> Syncing...'; this.form.submit();">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Sync Accounts Now</span>
                </button>
            </form>

            <!-- Full Sync Option Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-gear"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.8125rem;">
                    <li>
                        <form action="{{ route('admin.salesforce_accounts.sync') }}" method="POST">
                            @csrf
                            <input type="hidden" name="full" value="1">
                            <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2" onclick="return confirm('Force full sync will scan all available Salesforce Account records. Proceed?');">
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
            <form method="GET" action="{{ route('admin.salesforce_accounts.index') }}" class="row g-2 align-items-center">
                <!-- Search Keyword -->
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white text-muted border-end-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search account name, website, phone, city..." value="{{ $search }}">
                    </div>
                </div>

                <!-- Type Filter -->
                <div class="col-6 col-md-2">
                    <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        @foreach($types as $tp)
                            <option value="{{ $tp }}" {{ $selectedType === $tp ? 'selected' : '' }}>{{ $tp }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Industry Filter -->
                <div class="col-6 col-md-2">
                    <select name="industry" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Industries</option>
                        @foreach($industries as $ind)
                            <option value="{{ $ind }}" {{ $selectedIndustry === $ind ? 'selected' : '' }}>{{ $ind }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Records per Page -->
                <div class="col-6 col-md-2">
                    <select name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10 per page</option>
                        <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25 per page</option>
                        <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per page</option>
                        <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 per page</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="col-6 col-md-2 d-flex gap-1.5 justify-content-end">
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-50">Filter</button>
                    <a href="{{ route('admin.salesforce_accounts.index') }}" class="btn btn-light btn-sm w-50 border text-secondary" title="Reset Filters">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Accounts Table Card -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold text-zinc-900" style="font-size: 0.875rem;">
                <i class="bi bi-buildings text-primary me-1.5"></i> Synchronized Accounts Directory
            </span>
            <span class="small text-secondary">
                Showing {{ $accounts->firstItem() ?? 0 }} to {{ $accounts->lastItem() ?? 0 }} of {{ number_format($accounts->total()) }} records
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4" style="min-width: 200px;">Account Name</th>
                            <th style="min-width: 140px;">Industry</th>
                            <th style="min-width: 130px;">Type</th>
                            <th style="min-width: 130px;">Phone</th>
                            <th style="min-width: 150px;">Website</th>
                            <th style="min-width: 160px;">Billing Location</th>
                            <th style="min-width: 100px;">Contacts</th>
                            <th style="min-width: 150px;">Prime Owner (SF User)</th>
                            <th style="min-width: 130px;">Standard Owner</th>
                            <th style="min-width: 140px;">SF Modified</th>
                            <th class="pe-4 text-end" style="min-width: 140px;">Local Synced</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $account)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center text-primary fw-bold" style="width: 32px; height: 32px; font-size: 0.75rem; flex-shrink: 0;">
                                        {{ strtoupper(substr($account->name ?: 'A', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-zinc-900">{{ $account->name }}</div>
                                        <div class="text-secondary small font-monospace" style="font-size: 0.72rem;">{{ $account->salesforce_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($account->industry)
                                    <span class="badge bg-secondary-soft text-zinc-800">{{ $account->industry }}</span>
                                @else
                                    <span class="text-zinc-400 fst-italic">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-secondary small">{{ $account->type ?: '-' }}</span>
                            </td>
                            <td>
                                @if($account->phone)
                                    <span class="text-zinc-800 d-inline-flex align-items-center gap-1 font-monospace" style="font-size: 0.8rem;">
                                        <i class="bi bi-telephone text-zinc-400"></i> {{ $account->phone }}
                                    </span>
                                @else
                                    <span class="text-zinc-400 fst-italic">-</span>
                                @endif
                            </td>
                            <td>
                                @if($account->website)
                                    @php
                                        $url = str_starts_with($account->website, 'http') ? $account->website : 'https://' . $account->website;
                                    @endphp
                                    <a href="{{ $url }}" target="_blank" class="text-decoration-none text-zinc-900 fw-medium d-inline-flex align-items-center gap-1 text-truncate" style="max-width: 160px;" title="{{ $account->website }}">
                                        <i class="bi bi-globe text-zinc-400"></i> {{ $account->website }}
                                    </a>
                                @else
                                    <span class="text-zinc-400 fst-italic">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="text-zinc-800 small">
                                    {{ $account->billing_city ?: '' }} {{ $account->billing_state ? ', ' . $account->billing_state : '' }}
                                    @if($account->billing_country)
                                        <div><span class="text-muted" style="font-size: 0.72rem;">{{ $account->billing_country }}</span></div>
                                    @elseif(!$account->billing_city && !$account->billing_state)
                                        <span class="text-zinc-400 fst-italic">-</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <a href="{{ route('admin.salesforce_contacts.index', ['search' => $account->name]) }}" class="badge bg-primary-soft text-decoration-none">
                                    <i class="bi bi-people me-1"></i> {{ $account->contacts_count }}
                                </a>
                            </td>
                            <td>
                                @if($account->primeOwner)
                                    <a href="{{ route('admin.salesforce_sf_users.index', ['search' => $account->primeOwner->name]) }}" class="text-decoration-none fw-medium text-zinc-900 d-inline-flex align-items-center gap-1" title="Prime Owner: {{ $account->primeOwner->name }}">
                                        <i class="bi bi-person-badge text-primary"></i> {{ $account->primeOwner->name }}
                                    </a>
                                @elseif($account->prime_owner_id)
                                    <code class="text-muted small">{{ $account->prime_owner_id }}</code>
                                @else
                                    <span class="text-zinc-400 fst-italic">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="text-secondary small" title="{{ $account->owner_id }}">
                                    {{ $account->owner ? $account->owner->name : ($account->owner_id ?: '-') }}
                                </div>
                            </td>
                            <td>
                                <span class="text-secondary small text-nowrap">
                                    {{ $account->salesforce_updated_at ? $account->salesforce_updated_at->format('M d, Y h:i A') : '-' }}
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <span class="text-secondary small text-nowrap" title="{{ $account->synced_at }}">
                                    {{ $account->synced_at ? $account->synced_at->diffForHumans() : '-' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 48px; height: 48px;">
                                    <i class="bi bi-building text-muted fs-4"></i>
                                </div>
                                <h6 class="fw-semibold text-zinc-800 mb-1">No Synchronized Accounts Found</h6>
                                <p class="text-secondary small mb-3">
                                    @if($search || $selectedType || $selectedIndustry)
                                        No accounts matched the search criteria. Try clearing your filters.
                                    @else
                                        Click "Sync Accounts Now" or wait for the scheduled cron job to import accounts from Salesforce.
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
                Showing {{ $accounts->firstItem() ?? 0 }} to {{ $accounts->lastItem() ?? 0 }} of {{ number_format($accounts->total()) }} accounts
            </div>
            <div>
                {{ $accounts->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection
