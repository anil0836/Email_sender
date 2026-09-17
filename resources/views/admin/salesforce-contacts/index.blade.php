@extends('layouts.app')

@section('page_title', 'Salesforce Synchronized Contacts')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h4 fw-bold text-zinc-900 mb-0">Salesforce Synchronized Contacts</h1>
                <span class="badge bg-primary-soft">
                    <i class="bi bi-people-fill me-1"></i> {{ number_format($totalLocalContacts) }} Synced in MySQL
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
                Automated background import from Salesforce Contact object into local database via Laravel Scheduler & Cron.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Manual Sync Form -->
            <form action="{{ route('admin.salesforce_contacts.sync') }}" method="POST" id="manual-sync-contacts-form" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm shadow-sm d-flex align-items-center gap-1.5" id="sync-contacts-btn" onclick="this.disabled=true; this.innerHTML='<span class=\"spinner-border spinner-border-sm me-1\"></span> Syncing...'; this.form.submit();">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Sync Contacts Now</span>
                </button>
            </form>

            <!-- Full Sync Option Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-gear"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.8125rem;">
                    <li>
                        <form action="{{ route('admin.salesforce_contacts.sync') }}" method="POST">
                            @csrf
                            <input type="hidden" name="full" value="1">
                            <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2" onclick="return confirm('Force full sync will scan all available Salesforce Contact records. Proceed?');">
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
            <form method="GET" action="{{ route('admin.salesforce_contacts.index') }}" class="row g-2 align-items-center">
                <!-- Search Keyword -->
                <div class="col-12 col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white text-muted border-end-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search name, email, phone, title, department..." value="{{ $search }}">
                    </div>
                </div>

                <!-- Lead Source Filter -->
                <div class="col-6 col-md-3">
                    <select name="lead_source" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Lead Sources</option>
                        @foreach($leadSources as $src)
                            <option value="{{ $src }}" {{ $selectedSource === $src ? 'selected' : '' }}>{{ $src }}</option>
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
                <div class="col-12 col-md-2 d-flex gap-1.5 justify-content-end">
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-50">Filter</button>
                    <a href="{{ route('admin.salesforce_contacts.index') }}" class="btn btn-light btn-sm w-50 border text-secondary" title="Reset Filters">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Contacts Table Card -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold text-zinc-900" style="font-size: 0.875rem;">
                <i class="bi bi-person-rolodex text-primary me-1.5"></i> Synchronized Contacts Directory
            </span>
            <span class="small text-secondary">
                Showing {{ $contacts->firstItem() ?? 0 }} to {{ $contacts->lastItem() ?? 0 }} of {{ number_format($contacts->total()) }} records
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4" style="min-width: 180px;">Contact Name</th>
                            <th style="min-width: 170px;">Account</th>
                            <th style="min-width: 180px;">Email</th>
                            <th style="min-width: 130px;">Phone</th>
                            <th style="min-width: 140px;">Title & Dept</th>
                            <th style="min-width: 130px;">Lead Source</th>
                            <th style="min-width: 140px;">Mailing Location</th>
                            <th style="min-width: 150px;">Prime Owner (SF User)</th>
                            <th style="min-width: 130px;">Standard Owner</th>
                            <th style="min-width: 140px;">SF Modified</th>
                            <th class="pe-4 text-end" style="min-width: 140px;">Local Synced</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contacts as $contact)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center text-primary fw-bold" style="width: 32px; height: 32px; font-size: 0.75rem; flex-shrink: 0;">
                                        {{ strtoupper(substr($contact->name ?: $contact->last_name ?: 'C', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-zinc-900">{{ $contact->name ?: trim($contact->first_name . ' ' . $contact->last_name) ?: 'Unnamed Contact' }}</div>
                                        <div class="text-secondary small font-monospace" style="font-size: 0.72rem;">{{ $contact->salesforce_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($contact->account)
                                    <a href="{{ route('admin.salesforce_accounts.index', ['search' => $contact->account->name]) }}" class="text-decoration-none fw-medium text-zinc-900 d-inline-flex align-items-center gap-1 text-truncate" style="max-width: 170px;" title="{{ $contact->account->name }}">
                                        <i class="bi bi-building text-zinc-400"></i> {{ $contact->account->name }}
                                    </a>
                                @elseif($contact->account_id)
                                    <code class="text-muted small">{{ $contact->account_id }}</code>
                                @else
                                    <span class="text-zinc-400 fst-italic">-</span>
                                @endif
                            </td>
                            <td>
                                @if($contact->email)
                                    <a href="mailto:{{ $contact->email }}" class="text-decoration-none text-zinc-900 fw-medium d-inline-flex align-items-center gap-1 text-truncate" style="max-width: 180px;" title="{{ $contact->email }}">
                                        <i class="bi bi-envelope text-zinc-400"></i> {{ $contact->email }}
                                    </a>
                                @else
                                    <span class="text-zinc-400 fst-italic">-</span>
                                @endif
                            </td>
                            <td>
                                @if($contact->phone || $contact->mobile_phone)
                                    <span class="text-zinc-800 d-inline-flex align-items-center gap-1 font-monospace" style="font-size: 0.8rem;">
                                        <i class="bi bi-telephone text-zinc-400"></i> {{ $contact->phone ?: $contact->mobile_phone }}
                                    </span>
                                @else
                                    <span class="text-zinc-400 fst-italic">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="text-zinc-800 small">{{ $contact->title ?: '-' }}</div>
                                @if($contact->department)
                                    <div class="text-muted small" style="font-size: 0.72rem;">{{ $contact->department }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="text-secondary small">{{ $contact->lead_source ?: '-' }}</span>
                            </td>
                            <td>
                                <div class="text-zinc-800 small">
                                    {{ $contact->mailing_city ?: '' }} {{ $contact->mailing_state ? ', ' . $contact->mailing_state : '' }}
                                    @if($contact->mailing_country)
                                        <div><span class="text-muted" style="font-size: 0.72rem;">{{ $contact->mailing_country }}</span></div>
                                    @elseif(!$contact->mailing_city && !$contact->mailing_state)
                                        <span class="text-zinc-400 fst-italic">-</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($contact->primeOwner)
                                    <a href="{{ route('admin.salesforce_sf_users.index', ['search' => $contact->primeOwner->name]) }}" class="text-decoration-none fw-medium text-zinc-900 d-inline-flex align-items-center gap-1" title="Prime Owner: {{ $contact->primeOwner->name }}">
                                        <i class="bi bi-person-badge text-primary"></i> {{ $contact->primeOwner->name }}
                                    </a>
                                @elseif($contact->prime_owner_id)
                                    <code class="text-muted small">{{ $contact->prime_owner_id }}</code>
                                @else
                                    <span class="text-zinc-400 fst-italic">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="text-secondary small" title="{{ $contact->owner_id }}">
                                    {{ $contact->owner ? $contact->owner->name : ($contact->owner_id ?: '-') }}
                                </div>
                            </td>
                            <td>
                                <span class="text-secondary small text-nowrap">
                                    {{ $contact->salesforce_updated_at ? $contact->salesforce_updated_at->format('M d, Y h:i A') : '-' }}
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <span class="text-secondary small text-nowrap" title="{{ $contact->synced_at }}">
                                    {{ $contact->synced_at ? $contact->synced_at->diffForHumans() : '-' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 48px; height: 48px;">
                                    <i class="bi bi-person-x text-muted fs-4"></i>
                                </div>
                                <h6 class="fw-semibold text-zinc-800 mb-1">No Synchronized Contacts Found</h6>
                                <p class="text-secondary small mb-3">
                                    @if($search || $selectedSource)
                                        No contacts matched the search criteria. Try clearing your filters.
                                    @else
                                        Click "Sync Contacts Now" or wait for the scheduled cron job to import contacts from Salesforce.
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
                Showing {{ $contacts->firstItem() ?? 0 }} to {{ $contacts->lastItem() ?? 0 }} of {{ number_format($contacts->total()) }} contacts
            </div>
            <div>
                {{ $contacts->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection
