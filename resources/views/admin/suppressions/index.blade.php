@extends('layouts.app')

@section('page_title', 'Global Email Suppressions')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h4 fw-bold text-zinc-900 mb-0">Global Email Suppressions</h1>
                <span class="badge bg-danger-soft text-rose-700">
                    <i class="bi bi-shield-x me-1"></i> {{ number_format($statistics['total_active'] ?? 0) }} Active Suppressed
                </span>
            </div>
            <p class="text-secondary small mb-0">
                Centralized global unsubscribe and delivery suppression database protecting campaign deliverability.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-danger btn-sm shadow-sm d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#manualSuppressModal">
                <i class="bi bi-plus-circle"></i>
                <span>Suppress Email</span>
            </button>
            <a href="{{ route('admin.suppressions.import') }}" class="btn btn-outline-primary btn-sm shadow-sm d-flex align-items-center gap-1.5">
                <i class="bi bi-file-earmark-arrow-up"></i>
                <span>Import CSV</span>
            </a>
            <a href="{{ route('admin.suppressions.export') }}" class="btn btn-outline-secondary btn-sm shadow-sm d-flex align-items-center gap-1.5">
                <i class="bi bi-download"></i>
                <span>Export CSV</span>
            </a>
        </div>
    </div>

    <!-- Metrics Cards Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card border shadow-sm h-100">
                <div class="card-body p-3">
                    <span class="stat-label text-zinc-500 d-block mb-1" style="font-size: 0.72rem; font-weight: 600;">ACTIVE SUPPRESSED</span>
                    <h4 class="fw-bold text-zinc-900 mb-0">{{ number_format($statistics['total_active'] ?? 0) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border shadow-sm h-100">
                <div class="card-body p-3">
                    <span class="stat-label text-amber-700 d-block mb-1" style="font-size: 0.72rem; font-weight: 600;">UNSUBSCRIBED</span>
                    <h4 class="fw-bold text-amber-800 mb-0">{{ number_format($statistics['unsubscribed'] ?? 0) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border shadow-sm h-100">
                <div class="card-body p-3">
                    <span class="stat-label text-rose-700 d-block mb-1" style="font-size: 0.72rem; font-weight: 600;">HARD BOUNCES</span>
                    <h4 class="fw-bold text-rose-800 mb-0">{{ number_format($statistics['hard_bounces'] ?? 0) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border shadow-sm h-100">
                <div class="card-body p-3">
                    <span class="stat-label text-rose-900 d-block mb-1" style="font-size: 0.72rem; font-weight: 600;">SPAM COMPLAINTS</span>
                    <h4 class="fw-bold text-rose-950 mb-0">{{ number_format($statistics['complaints'] ?? 0) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border shadow-sm h-100">
                <div class="card-body p-3">
                    <span class="stat-label text-indigo-700 d-block mb-1" style="font-size: 0.72rem; font-weight: 600;">MANUAL / CSV</span>
                    <h4 class="fw-bold text-indigo-900 mb-0">{{ number_format($statistics['manual_suppressions'] ?? 0) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border shadow-sm h-100">
                <div class="card-body p-3">
                    <span class="stat-label text-emerald-700 d-block mb-1" style="font-size: 0.72rem; font-weight: 600;">RESUBSCRIBED</span>
                    <h4 class="fw-bold text-emerald-800 mb-0">{{ number_format($statistics['resubscribed'] ?? 0) }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="{{ route('admin.suppressions.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search by email, reason, or message ID..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="suppressed" {{ $status === 'suppressed' ? 'selected' : '' }}>Status: Active Suppressed</option>
                        <option value="resubscribed" {{ $status === 'resubscribed' ? 'selected' : '' }}>Status: Resubscribed</option>
                        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>Status: All Records</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="source" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Sources</option>
                        @foreach($sources as $s)
                            <option value="{{ $s }}" {{ $source === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="reason" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Reasons</option>
                        @foreach($reasons as $r)
                            <option value="{{ $r }}" {{ $reason === $r ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($r, 24) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-dark btn-sm flex-fill">Filter</button>
                    <a href="{{ route('admin.suppressions.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Suppressions Table Card -->
    <div class="card border shadow-sm">
        <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
            <h6 class="fw-bold text-zinc-900 mb-0">
                <i class="bi bi-list-check me-1.5 text-primary"></i> Suppressed Email Directory
            </h6>
            <span class="text-zinc-500 small">Showing {{ $suppressions->firstItem() ?? 0 }} - {{ $suppressions->lastItem() ?? 0 }} of {{ $suppressions->total() }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Recipient Email</th>
                        <th>Reason</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Suppressed At</th>
                        <th>Audit Trail</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppressions as $item)
                        <tr>
                            <td class="ps-4">
                                <div class="fw-semibold text-zinc-900">{{ $item->email }}</div>
                                @if($item->provider_message_id)
                                    <small class="text-zinc-400 font-monospace" style="font-size: 0.68rem;">Msg: {{ $item->provider_message_id }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-secondary-soft text-zinc-700" style="font-size: 0.72rem;">
                                    {{ $item->reason }}
                                </span>
                            </td>
                            <td>
                                @if($item->source === 'pabbly_webhook')
                                    <span class="badge bg-info-soft text-indigo-700">Pabbly Webhook</span>
                                @elseif($item->source === 'csv_import')
                                    <span class="badge bg-secondary-soft text-zinc-700">CSV Import</span>
                                @elseif($item->source === 'admin_manual')
                                    <span class="badge bg-warning-soft text-amber-900">Admin Manual</span>
                                @else
                                    <span class="badge bg-secondary-soft text-zinc-600">{{ $item->source }}</span>
                                @endif
                            </td>
                            <td>
                                @if($item->status === 'suppressed')
                                    <span class="badge bg-danger-soft text-rose-700"><i class="bi bi-x-circle me-0.5"></i> Suppressed</span>
                                @else
                                    <span class="badge bg-success-soft text-emerald-700"><i class="bi bi-check-circle me-0.5"></i> Resubscribed</span>
                                @endif
                            </td>
                            <td>
                                <div style="font-size: 0.75rem;" class="text-zinc-600">
                                    {{ $item->added_at ? $item->added_at->format('M d, Y h:i A') : $item->created_at->format('M d, Y h:i A') }}
                                </div>
                                <small class="text-zinc-400" style="font-size: 0.68rem;">
                                    {{ $item->added_at ? $item->added_at->diffForHumans() : $item->created_at->diffForHumans() }}
                                </small>
                            </td>
                            <td>
                                @if($item->status === 'resubscribed')
                                    <small class="text-emerald-700 d-block" style="font-size: 0.72rem;">
                                        <i class="bi bi-check2"></i> Resubscribed {{ $item->resubscribed_at ? $item->resubscribed_at->format('M d, Y') : '' }}
                                        @if($item->resubscribedByUser)
                                            by <strong>{{ $item->resubscribedByUser->username }}</strong>
                                        @endif
                                    </small>
                                @elseif($item->createdByUser)
                                    <small class="text-zinc-500" style="font-size: 0.72rem;">
                                        By {{ $item->createdByUser->username }}
                                    </small>
                                @else
                                    <span class="text-zinc-400" style="font-size: 0.72rem;">Automated</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                @if($item->status === 'suppressed')
                                    <button type="button" class="btn btn-outline-success btn-xs" 
                                            onclick="openResubscribeModal({{ $item->id }}, '{{ addslashes($item->email) }}')">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Resubscribe
                                    </button>
                                @else
                                    <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">Allowed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-shield-check fs-1 text-zinc-300 d-block mb-2"></i>
                                No suppression records match your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($suppressions->hasPages())
            <div class="card-footer bg-white py-3 px-4 border-top">
                {{ $suppressions->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Manual Suppress Modal -->
<div class="modal fade" id="manualSuppressModal" tabindex="-1" aria-labelledby="manualSuppressModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.suppressions.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold text-zinc-900" id="manualSuppressModalTitle">
                        <i class="bi bi-shield-x text-danger me-1"></i> Manually Suppress Email
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-zinc-700">Recipient Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control form-control-sm" placeholder="customer@example.com" required>
                        <small class="text-muted" style="font-size: 0.72rem;">Email will be normalized (trimmed and lowercased) automatically.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-zinc-700">Suppression Reason <span class="text-danger">*</span></label>
                        <select name="reason" class="form-select form-select-sm" required>
                            <option value="Customer Unsubscribe Request">Customer Unsubscribe Request</option>
                            <option value="GDPR / Compliance Opt-Out">GDPR / Compliance Opt-Out</option>
                            <option value="Spam Complaint">Spam Complaint</option>
                            <option value="Hard Bounce / Invalid Domain">Hard Bounce / Invalid Domain</option>
                            <option value="Administrative Block">Administrative Block</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-zinc-700">Audit Note / Explanation (Optional)</label>
                        <textarea name="note" class="form-control form-control-sm" rows="2" placeholder="Requested via support ticket #1234..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Confirm Suppression</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Resubscribe Confirmation Modal -->
<div class="modal fade" id="resubscribeModal" tabindex="-1" aria-labelledby="resubscribeModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="resubscribeForm" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold text-zinc-900" id="resubscribeModalTitle">
                        <i class="bi bi-arrow-counterclockwise text-success me-1"></i> Explicit Resubscription Authorization
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 px-3 small mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Legal Compliance Notice:</strong> Only resubscribe recipients who have provided documented, explicit opt-in consent to resume receiving marketing communications.
                    </div>
                    <p class="small text-zinc-700 mb-3">
                        Are you sure you want to restore sending eligibility for <strong id="resubscribeEmailSpan" class="text-zinc-900"></strong>?
                    </p>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-zinc-700">Authorization Remark / Consent Proof <span class="text-danger">*</span></label>
                        <input type="text" name="remark" class="form-control form-control-sm" placeholder="User verified re-opt-in via portal request..." required>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">Authorize & Resubscribe</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openResubscribeModal(id, email) {
    const form = document.getElementById('resubscribeForm');
    form.action = `/admin/email-suppressions/${id}/resubscribe`;
    document.getElementById('resubscribeEmailSpan').innerText = email;
    const modal = new bootstrap.Modal(document.getElementById('resubscribeModal'));
    modal.show();
}
</script>
@endsection
