@extends('layouts.app')

@section('page_title', 'Salesforce Synchronized Users')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h4 fw-bold text-zinc-900 mb-0">Salesforce Synchronized Users</h1>
                <span class="badge bg-primary-soft">
                    <i class="bi bi-people-fill me-1"></i> {{ number_format($totalLocalUsers) }} Synced in MySQL
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
                Automated background import from Salesforce User object into local database via Laravel Scheduler & Cron.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Manual Sync Form -->
            <form action="{{ route('admin.salesforce_users.sync') }}" method="POST" id="manual-sync-users-form" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm shadow-sm d-flex align-items-center gap-1.5" id="sync-users-btn" onclick="this.disabled=true; this.innerHTML='<span class=\"spinner-border spinner-border-sm me-1\"></span> Syncing...'; this.form.submit();">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Sync Users Now</span>
                </button>
            </form>

            <!-- Full Sync Option Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-gear"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.8125rem;">
                    <li>
                        <form action="{{ route('admin.salesforce_users.sync') }}" method="POST">
                            @csrf
                            <input type="hidden" name="full" value="1">
                            <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2" onclick="return confirm('Force full sync will scan all available Salesforce User records. Proceed?');">
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
            <form method="GET" action="{{ route('admin.salesforce_users.index') }}" class="row g-2 align-items-center">
                <!-- Search Keyword -->
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white text-muted border-end-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search name, username, email, department..." value="{{ $search }}">
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

                <!-- User Type Filter -->
                <div class="col-6 col-md-2">
                    <select name="user_type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All User Types</option>
                        @foreach($userTypes as $ut)
                            <option value="{{ $ut }}" {{ $selectedUserType === $ut ? 'selected' : '' }}>{{ $ut }}</option>
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
                    <a href="{{ route('admin.salesforce_users.index') }}" class="btn btn-light btn-sm w-50 border text-secondary" title="Reset Filters">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Users Table Card -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold text-zinc-900" style="font-size: 0.875rem;">
                <i class="bi bi-people text-primary me-1.5"></i> Synchronized Users Directory
            </span>
            <span class="small text-secondary">
                Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ number_format($users->total()) }} records
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4" style="min-width: 180px;">User Name</th>
                            <th style="min-width: 180px;">Salesforce Username / Email</th>
                            <th style="min-width: 140px;">Title & Dept</th>
                            <th style="min-width: 130px;">Company</th>
                            <th style="min-width: 110px;">Status</th>
                            <th style="min-width: 130px;">User Type</th>
                            <th style="min-width: 140px;">Manager</th>
                            <th style="min-width: 150px;">Owned Entities</th>
                            <th style="min-width: 140px;">SF Modified</th>
                            <th class="pe-4 text-end" style="min-width: 140px;">Local Synced</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center text-primary fw-bold" style="width: 32px; height: 32px; font-size: 0.75rem; flex-shrink: 0;">
                                        {{ strtoupper(substr($user->name ?: $user->username ?: 'U', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-zinc-900">{{ $user->name ?: trim($user->first_name . ' ' . $user->last_name) ?: 'Unnamed User' }}</div>
                                        <div class="text-secondary small font-monospace" style="font-size: 0.72rem;">{{ $user->salesforce_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($user->email)
                                    <a href="mailto:{{ $user->email }}" class="text-decoration-none text-zinc-900 fw-medium d-inline-flex align-items-center gap-1 text-truncate" style="max-width: 200px;" title="{{ $user->email }}">
                                        <i class="bi bi-envelope text-zinc-400"></i> {{ $user->email }}
                                    </a>
                                @endif
                                @if($user->username && $user->username !== $user->email)
                                    <div class="text-muted small text-truncate" style="max-width: 200px; font-size: 0.72rem;" title="{{ $user->username }}">
                                        {{ $user->username }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="text-zinc-800 small">{{ $user->title ?: '-' }}</div>
                                @if($user->department)
                                    <div class="text-muted small" style="font-size: 0.72rem;">{{ $user->department }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="text-secondary small">{{ $user->company_name ?: '-' }}</span>
                            </td>
                            <td>
                                @if($user->is_active)
                                    <span class="badge bg-success-soft"><i class="bi bi-check-circle me-1"></i>Active</span>
                                @else
                                    <span class="badge bg-secondary-soft text-muted"><i class="bi bi-dash-circle me-1"></i>Inactive</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-secondary-soft text-zinc-800">{{ $user->user_type ?: 'Standard' }}</span>
                            </td>
                            <td>
                                @if($user->manager)
                                    <div class="fw-medium text-zinc-900 small">{{ $user->manager->name ?: trim($user->manager->first_name . ' ' . $user->manager->last_name) ?: $user->manager->username }}</div>
                                    <div class="text-secondary small font-monospace" style="font-size: 0.72rem;">{{ $user->manager_id }}</div>
                                @elseif($user->manager_id)
                                    <span class="badge bg-light text-zinc-800 border font-monospace" style="font-size: 0.72rem;">{{ $user->manager_id }}</span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <span class="badge bg-primary-soft" title="Owned Leads">
                                        <i class="bi bi-funnel me-0.5"></i> {{ $user->leads_count }} Leads
                                    </span>
                                    <span class="badge bg-primary-soft" title="Owned Accounts">
                                        <i class="bi bi-buildings me-0.5"></i> {{ $user->accounts_count }} Accs
                                    </span>
                                    <span class="badge bg-primary-soft" title="Owned Contacts">
                                        <i class="bi bi-person me-0.5"></i> {{ $user->contacts_count }} Cons
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="text-secondary small text-nowrap">
                                    {{ $user->salesforce_updated_at ? $user->salesforce_updated_at->format('M d, Y h:i A') : '-' }}
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <span class="text-secondary small text-nowrap" title="{{ $user->synced_at }}">
                                    {{ $user->synced_at ? $user->synced_at->diffForHumans() : '-' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 48px; height: 48px;">
                                    <i class="bi bi-people text-muted fs-4"></i>
                                </div>
                                <h6 class="fw-semibold text-zinc-800 mb-1">No Synchronized Users Found</h6>
                                <p class="text-secondary small mb-3">
                                    @if($search || $selectedActive !== null || $selectedUserType)
                                        No users matched the search criteria. Try clearing your filters.
                                    @else
                                        Click "Sync Users Now" or wait for the scheduled cron job to import users from Salesforce.
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
                Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ number_format($users->total()) }} users
            </div>
            <div>
                {{ $users->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection
