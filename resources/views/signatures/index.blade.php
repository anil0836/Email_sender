@extends('layouts.app')

@section('page_title', 'Email Signatures')

@section('content')
<style>
    .signature-card {
        transition: all 0.15s ease;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-card);
        background: #ffffff;
    }
    .signature-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    .signature-preview-box {
        background-color: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        padding: 16px;
    }
</style>

<div class="row g-4">
    <!-- Header -->
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="fw-bold text-zinc-900 mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-pen-fill" style="color: var(--primary-color, #4f46e5);"></i> Email Signatures
                </h4>
                <p class="text-zinc-500 mb-0" style="font-size: 0.825rem;">
                    Manage reusable email signatures and contact blocks for your outbound campaigns.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-primary btn-sm" onclick="openCreateModal()">
                    <i class="bi bi-plus-lg"></i> Create Signature
                </button>
                <a href="{{ route('campaign_create_view') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil-square"></i> Create Campaign
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="col-12">
        <div class="alert alert-success alert-dismissible fade show py-2 px-3 d-flex align-items-center gap-2 mb-0" role="alert" style="font-size: 0.85rem;">
            <i class="bi bi-check-circle-fill"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.8rem;"></button>
        </div>
    </div>
    @endif

    <!-- Signatures List -->
    <div class="col-12">
        @if($signatures->isEmpty())
        <div class="card p-5 text-center" style="border-radius: var(--radius-card); border: 1px dashed var(--border-color);">
            <div class="mb-3 text-zinc-300">
                <i class="bi bi-pen" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-semibold text-zinc-800 mb-1">No Email Signatures Found</h5>
            <p class="text-zinc-500 mb-4" style="font-size: 0.85rem; max-width: 450px; margin: 0 auto;">
                Create your first signature block. You can automatically include it across all outbound campaigns using the <code>@{{Signature}}</code> merge field.
            </p>
            <div>
                <button type="button" class="btn btn-primary btn-sm px-4" onclick="openCreateModal()">
                    <i class="bi bi-plus-lg me-1"></i> Create Signature
                </button>
            </div>
        </div>
        @else
        <div class="row g-3">
            @foreach($signatures as $sig)
            <div class="col-12 col-lg-6">
                <div class="card signature-card h-100 p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h6 class="fw-bold text-zinc-900 mb-0">{{ $sig->name }}</h6>
                                @if($sig->is_default)
                                <span class="badge bg-primary-soft">
                                    <i class="bi bi-star-fill text-warning me-1"></i> Default
                                </span>
                                @endif
                            </div>
                            <div class="text-zinc-500 mt-1" style="font-size: 0.75rem;">
                                Created {{ $sig->created_at->diffForHumans() }}
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary py-1 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.825rem;">
                                <li>
                                    <button class="dropdown-item" onclick="openEditModal({{ $sig->id }})">
                                        <i class="bi bi-pencil me-2 text-zinc-500"></i> Edit
                                    </button>
                                </li>
                                @if(!$sig->is_default)
                                <li>
                                    <form action="{{ route('signatures.default', $sig->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="dropdown-item">
                                            <i class="bi bi-star me-2 text-warning"></i> Set as Default
                                        </button>
                                    </form>
                                </li>
                                @endif
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ route('signatures.destroy', $sig->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this signature?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-trash me-2"></i> Delete
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Rendered Preview Block -->
                    <div class="signature-preview-box mb-3 flex-grow-1">
                        <div class="text-zinc-400 fw-semibold mb-2" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;">
                            Signature Preview
                        </div>
                        <div class="signature-rendered-content" style="background: #ffffff; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                            {!! $sig->renderHtml() !!}
                        </div>
                    </div>

                    <!-- Card Footer Actions -->
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="text-zinc-500" style="font-size: 0.75rem;">
                            Tag: <code class="text-primary">@{{Signature}}</code>
                        </span>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" onclick="openEditModal({{ $sig->id }})" style="font-size: 0.75rem;">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            @if(!$sig->is_default)
                            <form action="{{ route('signatures.default', $sig->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm py-1 px-2" style="font-size: 0.75rem;">
                                    <i class="bi bi-star"></i> Make Default
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

<!-- Modal: Create / Edit Signature -->
<div class="modal fade" id="signatureModal" tabindex="-1" aria-labelledby="signatureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--radius-card);">
            <form id="signatureForm" method="POST" action="{{ route('signatures.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <input type="hidden" id="signatureId" value="">

                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title fw-bold text-zinc-900" id="signatureModalLabel">
                        <i class="bi bi-pen-fill text-primary me-2"></i> Create Signature
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label text-zinc-700 fw-semibold" style="font-size: 0.8rem;">
                                Signature Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="name" id="sig_name" placeholder="e.g. Official Work Signature" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label text-zinc-700 fw-semibold" style="font-size: 0.8rem;">
                                Sender Name
                            </label>
                            <input type="text" class="form-control" name="sender_name" id="sig_sender_name" placeholder="e.g. John Doe" oninput="updateLivePreview()">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label text-zinc-700 fw-semibold" style="font-size: 0.8rem;">
                                Job Title
                            </label>
                            <input type="text" class="form-control" name="job_title" id="sig_job_title" placeholder="e.g. VP of Sales" oninput="updateLivePreview()">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label text-zinc-700 fw-semibold" style="font-size: 0.8rem;">
                                Company Name
                            </label>
                            <input type="text" class="form-control" name="company_name" id="sig_company_name" placeholder="e.g. Acme Corporation" oninput="updateLivePreview()">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label text-zinc-700 fw-semibold" style="font-size: 0.8rem;">
                                Email Address
                            </label>
                            <input type="email" class="form-control" name="email" id="sig_email" placeholder="e.g. john@example.com" oninput="updateLivePreview()">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label text-zinc-700 fw-semibold" style="font-size: 0.8rem;">
                                Phone Number
                            </label>
                            <input type="text" class="form-control" name="phone" id="sig_phone" placeholder="e.g. +1 (555) 234-5678" oninput="updateLivePreview()">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label text-zinc-700 fw-semibold" style="font-size: 0.8rem;">
                                Website URL
                            </label>
                            <input type="text" class="form-control" name="website" id="sig_website" placeholder="e.g. https://www.acme.com" oninput="updateLivePreview()">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label text-zinc-700 fw-semibold" style="font-size: 0.8rem;">
                                Office / Physical Address
                            </label>
                            <input type="text" class="form-control" name="address" id="sig_address" placeholder="e.g. 100 Main St, Suite 400, New York, NY" oninput="updateLivePreview()">
                        </div>

                        <div class="col-12">
                            <label class="form-label text-zinc-700 fw-semibold d-flex justify-content-between align-items-center" style="font-size: 0.8rem;">
                                <span>Custom HTML / Disclaimer (Optional)</span>
                                <small class="text-zinc-500 fw-normal">Overrides structured fields if provided</small>
                            </label>
                            <textarea class="form-control font-monospace" name="content" id="sig_content" rows="3" placeholder="Leave blank to use the automatically formatted layout above, or enter custom HTML." oninput="updateLivePreview()" style="font-size: 0.8rem;"></textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="sig_is_default">
                                <label class="form-check-label text-zinc-700 fw-medium" for="sig_is_default" style="font-size: 0.825rem;">
                                    Set as default signature for new campaigns
                                </label>
                            </div>
                        </div>

                        <!-- Live Preview Inside Modal -->
                        <div class="col-12 mt-3">
                            <label class="form-label text-zinc-700 fw-semibold" style="font-size: 0.8rem;">
                                Live Signature Preview
                            </label>
                            <div id="modalLivePreview" class="signature-rendered-content p-3 border rounded bg-white">
                                <span class="text-zinc-400 fst-italic">Fill in the fields above to see live preview...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top pt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4" id="saveSignatureBtn">
                        <i class="bi bi-check-lg me-1"></i> Save Signature
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let signatureModalInstance = null;

    document.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('signatureModal');
        if (modalEl) {
            signatureModalInstance = new bootstrap.Modal(modalEl);
        }
    });

    function openCreateModal() {
        document.getElementById('signatureForm').action = "{{ route('signatures.store') }}";
        document.getElementById('formMethod').value = "POST";
        document.getElementById('signatureId').value = "";
        document.getElementById('signatureModalLabel').innerHTML = '<i class="bi bi-pen-fill text-primary me-2"></i> Create Signature';
        document.getElementById('saveSignatureBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Save Signature';

        // Reset inputs
        document.getElementById('sig_name').value = '';
        document.getElementById('sig_sender_name').value = "{{ session('username') ?? (Auth::user()->name ?? '') }}";
        document.getElementById('sig_job_title').value = '';
        document.getElementById('sig_company_name').value = '';
        document.getElementById('sig_email').value = "{{ session('email') ?? (Auth::user()->email ?? '') }}";
        document.getElementById('sig_phone').value = '';
        document.getElementById('sig_website').value = '';
        document.getElementById('sig_address').value = '';
        document.getElementById('sig_content').value = '';
        document.getElementById('sig_is_default').checked = false;

        updateLivePreview();
        signatureModalInstance.show();
    }

    async function openEditModal(id) {
        try {
            const resp = await fetch(`/signatures/${id}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!resp.ok) throw new Error('Failed to load signature details');
            const data = await resp.json();
            const sig = data.signature;

            document.getElementById('signatureForm').action = `/signatures/${id}`;
            document.getElementById('formMethod').value = "PUT";
            document.getElementById('signatureId').value = id;
            document.getElementById('signatureModalLabel').innerHTML = '<i class="bi bi-pencil-square text-primary me-2"></i> Edit Signature';
            document.getElementById('saveSignatureBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Update Signature';

            document.getElementById('sig_name').value = sig.name || '';
            document.getElementById('sig_sender_name').value = sig.sender_name || '';
            document.getElementById('sig_job_title').value = sig.job_title || '';
            document.getElementById('sig_company_name').value = sig.company_name || '';
            document.getElementById('sig_email').value = sig.email || '';
            document.getElementById('sig_phone').value = sig.phone || '';
            document.getElementById('sig_website').value = sig.website || '';
            document.getElementById('sig_address').value = sig.address || '';
            document.getElementById('sig_content').value = sig.content || '';
            document.getElementById('sig_is_default').checked = Boolean(sig.is_default);

            updateLivePreview();
            signatureModalInstance.show();
        } catch (err) {
            alert('Error loading signature: ' + err.message);
        }
    }

    function updateLivePreview() {
        const customContent = document.getElementById('sig_content').value.trim();
        const previewEl = document.getElementById('modalLivePreview');

        if (customContent) {
            previewEl.innerHTML = customContent;
            return;
        }

        const senderName = document.getElementById('sig_sender_name').value.trim();
        const jobTitle = document.getElementById('sig_job_title').value.trim();
        const companyName = document.getElementById('sig_company_name').value.trim();
        const email = document.getElementById('sig_email').value.trim();
        const phone = document.getElementById('sig_phone').value.trim();
        const website = document.getElementById('sig_website').value.trim();
        const address = document.getElementById('sig_address').value.trim();

        if (!senderName && !jobTitle && !companyName && !email && !phone && !website && !address) {
            previewEl.innerHTML = '<span class="text-zinc-400 fst-italic">Fill in the fields above to see live preview...</span>';
            return;
        }

        let lines = [];
        if (senderName) {
            lines.push(`<div style="font-weight: 600; font-size: 14px; color: #111827;">${escapeHtml(senderName)}</div>`);
        }
        const titleCompany = [jobTitle, companyName].filter(Boolean);
        if (titleCompany.length > 0) {
            lines.push(`<div style="font-size: 13px; color: #4b5563; margin-top: 2px;">${escapeHtml(titleCompany.join(' | '))}</div>`);
        }
        let contactDetails = [];
        if (email) {
            contactDetails.push(`<a href="mailto:${escapeHtml(email)}" style="color: #2563eb; text-decoration: none;">${escapeHtml(email)}</a>`);
        }
        if (phone) {
            contactDetails.push(`<span>${escapeHtml(phone)}</span>`);
        }
        if (website) {
            const url = website.startsWith('http') ? website : 'https://' + website;
            contactDetails.push(`<a href="${escapeHtml(url)}" target="_blank" style="color: #2563eb; text-decoration: none;">${escapeHtml(website)}</a>`);
        }
        if (contactDetails.length > 0) {
            lines.push(`<div style="font-size: 12px; color: #6b7280; margin-top: 6px;">${contactDetails.join(' &bull; ')}</div>`);
        }
        if (address) {
            lines.push(`<div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">${escapeHtml(address).replace(/\\n/g, '<br>')}</div>`);
        }

        previewEl.innerHTML = `<div class="email-signature" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin-top: 10px; padding-top: 10px; border-top: 1px solid #e5e7eb; color: #374151; line-height: 1.4;">${lines.join('')}</div>`;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
</script>
@endsection
