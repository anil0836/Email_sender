@extends('layouts.app')

@section('page_title', 'Import Suppression CSV')

@section('content')
<div class="container-fluid px-0" style="max-width: 900px;">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h4 fw-bold text-zinc-900 mb-0">Import Suppression CSV</h1>
                <span class="badge bg-secondary-soft text-zinc-700">Bulk Compliance</span>
            </div>
            <p class="text-secondary small mb-0">
                Bulk upload unsubscribed or suppressed email lists into the central suppression database.
            </p>
        </div>
        <a href="{{ route('admin.suppressions.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1.5">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Suppressions</span>
        </a>
    </div>

    @if(session('import_summary'))
        @php $sum = session('import_summary'); @endphp
        <div class="card border-success shadow-sm mb-4">
            <div class="card-header bg-success-soft py-3 px-4">
                <h6 class="fw-bold text-emerald-900 mb-0">
                    <i class="bi bi-check-circle-fill me-1 text-emerald-700"></i> CSV Import Execution Report
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 text-center">
                    <div class="col-4 col-md-2">
                        <span class="text-zinc-500 d-block small fw-semibold">TOTAL ROWS</span>
                        <h4 class="fw-bold text-zinc-900 mb-0">{{ number_format($sum['total_rows']) }}</h4>
                    </div>
                    <div class="col-4 col-md-2">
                        <span class="text-emerald-700 d-block small fw-semibold">VALID EMAILS</span>
                        <h4 class="fw-bold text-emerald-700 mb-0">{{ number_format($sum['valid_emails']) }}</h4>
                    </div>
                    <div class="col-4 col-md-2">
                        <span class="text-primary d-block small fw-semibold">NEW SUPPRESSED</span>
                        <h4 class="fw-bold text-primary mb-0">{{ number_format($sum['newly_suppressed']) }}</h4>
                    </div>
                    <div class="col-4 col-md-2">
                        <span class="text-zinc-500 d-block small fw-semibold">ALREADY BLOCKED</span>
                        <h4 class="fw-bold text-zinc-700 mb-0">{{ number_format($sum['already_suppressed']) }}</h4>
                    </div>
                    <div class="col-4 col-md-2">
                        <span class="text-amber-700 d-block small fw-semibold">DUPLICATES IN FILE</span>
                        <h4 class="fw-bold text-amber-700 mb-0">{{ number_format($sum['duplicate_in_file']) }}</h4>
                    </div>
                    <div class="col-4 col-md-2">
                        <span class="text-rose-700 d-block small fw-semibold">INVALID SYNTAX</span>
                        <h4 class="fw-bold text-rose-700 mb-0">{{ number_format($sum['invalid_emails']) }}</h4>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white py-2 px-4 border-top text-end">
                <a href="{{ route('admin.suppressions.index') }}" class="btn btn-sm btn-primary">
                    View Suppressions Directory <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    @endif

    <!-- Upload Card -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-4">
            <form action="{{ route('admin.suppressions.import.post') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <!-- Dropzone / File Picker Area -->
                <div class="mb-4">
                    <label class="form-label fw-semibold text-zinc-900 mb-2">Select CSV File to Upload <span class="text-danger">*</span></label>
                    <input type="file" name="file" class="form-control" accept=".csv,.txt" required>
                    <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Supported file formats: <code>.csv</code>, <code>.txt</code> (max 10MB, up to 100,000 rows processed in chunks).</small>
                </div>

                <!-- Default Reason -->
                <div class="mb-4">
                    <label class="form-label fw-semibold text-zinc-900 mb-1">Default Suppression Reason</label>
                    <input type="text" name="default_reason" class="form-control form-control-sm" placeholder="Bulk CSV Import" value="Bulk CSV Import">
                    <small class="text-muted" style="font-size: 0.72rem;">Applied to any email row that does not specify a custom reason column in the CSV.</small>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="{{ route('admin.suppressions.sample') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-filetype-csv me-1"></i> Download Sample CSV Format
                    </a>
                    <button type="submit" class="btn btn-primary btn-sm px-4" onclick="this.disabled=true; this.innerHTML='<span class=\"spinner-border spinner-border-sm me-1\"></span> Processing Import...'; this.form.submit();">
                        <i class="bi bi-upload me-1"></i> Process & Suppress
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Instructions / Format Card -->
    <div class="card border shadow-sm">
        <div class="card-header bg-light py-2.5 px-4 border-bottom">
            <h6 class="fw-semibold text-zinc-800 mb-0 small">
                <i class="bi bi-info-circle me-1 text-primary"></i> CSV Specification & Guidelines
            </h6>
        </div>
        <div class="card-body p-4 small text-zinc-700">
            <p class="mb-2">Your CSV file must include an <code>email</code> column. An optional <code>reason</code> column can be provided.</p>
            
            <div class="bg-light p-3 rounded-2 border font-monospace mb-3" style="font-size: 0.75rem;">
                email,reason<br>
                john.doe@example.com,Customer Unsubscribe Request<br>
                support.client@b2b.org,Spam Complaint<br>
                test.lead@proitbuyer.com,GDPR Opt-Out
            </div>

            <ul class="mb-0 ps-3">
                <li class="mb-1"><strong>Case-Insensitive Normalization:</strong> All emails are automatically trimmed and lowercased before deduplication.</li>
                <li class="mb-1"><strong>Idempotent:</strong> Re-uploading existing suppressed emails will safely skip insertion without creating duplicate records.</li>
                <li class="mb-1"><strong>Global Impact:</strong> Suppressed addresses will immediately be blocked from Campaign Composer, CSV pasted audiences, and Salesforce CRM dispatches.</li>
            </ul>
        </div>
    </div>
</div>
@endsection
