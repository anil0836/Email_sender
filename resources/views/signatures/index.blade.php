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
    .sig-style-card {
        transition: all 0.15s ease;
        border: 1.5px solid #e2e8f0 !important;
        cursor: pointer;
    }
    .sig-style-card:hover {
        border-color: #94a3b8 !important;
        background-color: #f8fafc;
    }
    .sig-style-card.active {
        border-color: #4f46e5 !important;
        background-color: #f5f3ff;
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
                Create your first signature block. You can automatically include it across all outbound campaigns using the <code>@{{Signature}}</code> merge field or insert styled single-line HTML directly.
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
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <h6 class="fw-bold text-zinc-900 mb-0">{{ $sig->name }}</h6>
                                @if($sig->is_default)
                                <span class="badge bg-primary-soft">
                                    <i class="bi bi-star-fill text-warning me-1"></i> Default
                                </span>
                                @endif

                                @if($sig->layout_style === 'single_line')
                                <span class="badge text-primary" style="background: #eff6ff; font-weight: 500;">
                                    <i class="bi bi-dash-lg me-1"></i> Single Line
                                </span>
                                @elseif($sig->layout_style === 'single_line_bar')
                                <span class="badge" style="background: #eef2ff; color: #4f46e5; font-weight: 500;">
                                    <i class="bi bi-layout-sidebar-inset me-1"></i> Accent Bar
                                </span>
                                @else
                                <span class="badge text-secondary" style="background: #f1f5f9; font-weight: 500;">
                                    <i class="bi bi-distribute-vertical me-1"></i> Multi-line
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
                                <li>
                                    <button class="dropdown-item" onclick="copySignatureHtml({{ $sig->id }})">
                                        <i class="bi bi-clipboard me-2 text-zinc-500"></i> Copy HTML
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
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-zinc-400 fw-semibold" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                Signature Preview
                            </span>
                            <small class="text-zinc-500" style="font-size: 0.7rem;">
                                {{ $sig->layout_style === 'single_line' ? 'Horizontal Single-Line' : ($sig->layout_style === 'single_line_bar' ? 'Single-Line with Accent Bar' : 'Stacked Multi-Line') }}
                            </small>
                        </div>
                        <div class="signature-rendered-content" id="sig-rendered-{{ $sig->id }}" style="background: #ffffff; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0; overflow-x: auto;">
                            {!! $sig->renderHtml() !!}
                        </div>
                    </div>

                    <!-- Card Footer Actions -->
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top flex-wrap gap-2">
                        <span class="text-zinc-500" style="font-size: 0.75rem;">
                            Tag: <code class="text-primary">@{{Signature}}</code>
                        </span>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" onclick="copySignatureHtml({{ $sig->id }})" title="Copy rendered HTML" style="font-size: 0.75rem;">
                                <i class="bi bi-clipboard"></i> Copy HTML
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" onclick="openEditModal({{ $sig->id }})" style="font-size: 0.75rem;">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            @if(!$sig->is_default)
                            <form action="{{ route('signatures.default', $sig->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm py-1 px-2" style="font-size: 0.75rem;">
                                    <i class="bi bi-star"></i> Default
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
                            <input type="text" class="form-control" name="name" id="sig_name" placeholder="e.g. Single-Line Sales Signature" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label text-zinc-700 fw-semibold" style="font-size: 0.8rem;">
                                Sender Name
                            </label>
                            <input type="text" class="form-control" name="sender_name" id="sig_sender_name" placeholder="e.g. John Doe" oninput="updateLivePreview()">
                        </div>

                        <!-- Layout Style Selector -->
                        <div class="col-12">
                            <label class="form-label text-zinc-700 fw-semibold d-flex justify-content-between align-items-center mb-1" style="font-size: 0.8rem;">
                                <span><i class="bi bi-layout-text-window-reverse text-primary me-1"></i> Signature Layout Style</span>
                                <span class="text-zinc-500 fw-normal" style="font-size: 0.75rem;">Single-line or classic multi-line</span>
                            </label>
                            <div class="row g-2">
                                <div class="col-12 col-md-4">
                                    <div class="card p-2 h-100 sig-style-card" id="card_style_single_line" onclick="selectLayoutStyle('single_line')">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="radio" name="layout_style" id="layout_single_line" value="single_line" checked onchange="onRadioLayoutChange()">
                                            <label class="form-check-label w-100 cursor-pointer" for="layout_single_line" style="font-size: 0.8rem;">
                                                <div class="fw-bold text-zinc-900"><i class="bi bi-dash-lg text-primary me-1"></i> Single Line (Inline)</div>
                                                <small class="text-zinc-500">Horizontal inline line separated by clean vertical dividers</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="card p-2 h-100 sig-style-card" id="card_style_single_line_bar" onclick="selectLayoutStyle('single_line_bar')">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="radio" name="layout_style" id="layout_single_line_bar" value="single_line_bar" onchange="onRadioLayoutChange()">
                                            <label class="form-check-label w-100 cursor-pointer" for="layout_single_line_bar" style="font-size: 0.8rem;">
                                                <div class="fw-bold text-zinc-900"><i class="bi bi-layout-sidebar-inset text-indigo me-1"></i> Single Line (Accent Bar)</div>
                                                <small class="text-zinc-500">Horizontal single line with stylish left accent bar container</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="card p-2 h-100 sig-style-card" id="card_style_standard" onclick="selectLayoutStyle('standard')">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="radio" name="layout_style" id="layout_standard" value="standard" onchange="onRadioLayoutChange()">
                                            <label class="form-check-label w-100 cursor-pointer" for="layout_standard" style="font-size: 0.8rem;">
                                                <div class="fw-bold text-zinc-900"><i class="bi bi-distribute-vertical text-secondary me-1"></i> Multi-line (Classic)</div>
                                                <small class="text-zinc-500">Traditional stacked block format with separate rows</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label text-zinc-700 fw-semibold" style="font-size: 0.8rem;">
                                Job Title
                            </label>
                            <input type="text" class="form-control" name="job_title" id="sig_job_title" placeholder="e.g. Head of Business Development" oninput="updateLivePreview()">
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
                            <textarea class="form-control font-monospace" name="content" id="sig_content" rows="2" placeholder="Leave blank to use the automatically formatted layout above, or enter custom HTML." oninput="updateLivePreview()" style="font-size: 0.8rem;"></textarea>
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
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label text-zinc-700 fw-semibold mb-0" style="font-size: 0.8rem;">
                                    Live Signature Preview
                                </label>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="selectLayoutStyle('single_line')">Single Line</button>
                                    <button type="button" class="btn btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="selectLayoutStyle('single_line_bar')">Accent Bar</button>
                                    <button type="button" class="btn btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="selectLayoutStyle('standard')">Multi-line</button>
                                </div>
                            </div>
                            <div id="modalLivePreview" class="signature-rendered-content p-3 border rounded bg-white" style="overflow-x: auto;">
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

    function selectLayoutStyle(style) {
        const radio = document.getElementById('layout_' + style);
        if (radio) {
            radio.checked = true;
        }
        updateStyleCardsActive(style);
        updateLivePreview();
    }

    function onRadioLayoutChange() {
        const style = getSelectedLayoutStyle();
        updateStyleCardsActive(style);
        updateLivePreview();
    }

    function updateStyleCardsActive(activeStyle) {
        ['single_line', 'single_line_bar', 'standard'].forEach(s => {
            const card = document.getElementById('card_style_' + s);
            if (card) {
                if (s === activeStyle) {
                    card.classList.add('active');
                } else {
                    card.classList.remove('active');
                }
            }
        });
    }

    function getSelectedLayoutStyle() {
        const checked = document.querySelector('input[name="layout_style"]:checked');
        return checked ? checked.value : 'single_line';
    }

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

        selectLayoutStyle('single_line');
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

            selectLayoutStyle(sig.layout_style || 'standard');
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

        const layoutStyle = getSelectedLayoutStyle();

        if (layoutStyle === 'single_line' || layoutStyle === 'single_line_bar') {
            // Build single-line elements
            const items = [];

            if (senderName) {
                items.push(`<span style="font-weight: 700; color: #0f172a; letter-spacing: -0.01em;">${escapeHtml(senderName)}</span>`);
            }

            if (jobTitle && companyName) {
                items.push(`<span style="color: #334155; font-weight: 500;">${escapeHtml(jobTitle)}</span> <span style="color: #64748b;">at</span> <span style="font-weight: 600; color: #0f172a;">${escapeHtml(companyName)}</span>`);
            } else if (jobTitle) {
                items.push(`<span style="color: #334155; font-weight: 500;">${escapeHtml(jobTitle)}</span>`);
            } else if (companyName) {
                items.push(`<span style="font-weight: 600; color: #0f172a;">${escapeHtml(companyName)}</span>`);
            }

            if (email) {
                items.push(`<a href="mailto:${escapeHtml(email)}" style="color: #2563eb; text-decoration: none; font-weight: 500;">${escapeHtml(email)}</a>`);
            }

            if (phone) {
                const phoneClean = phone.replace(/[^0-9+]/g, '');
                items.push(`<a href="tel:${escapeHtml(phoneClean)}" style="color: #475569; text-decoration: none;">${escapeHtml(phone)}</a>`);
            }

            if (website) {
                const url = website.startsWith('http') ? website : 'https://' + website;
                const displayUrl = website.replace(/^https?:\/\/(?:www\.)?/i, '').replace(/\/+$/, '');
                items.push(`<a href="${escapeHtml(url)}" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 500;">${escapeHtml(displayUrl)}</a>`);
            }

            if (address) {
                const flatAddress = escapeHtml(address.replace(/[\r\n]+/g, ', '));
                items.push(`<span style="color: #64748b; font-size: 12px;">${flatAddress}</span>`);
            }

            const sep = '<span class="sig-sep" style="color: #cbd5e1; margin: 0 7px; font-weight: 300;">|</span>';
            const joined = items.join(sep);

            if (layoutStyle === 'single_line_bar') {
                previewEl.innerHTML = `<div class="email-signature-single-line-bar" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 13px; line-height: 1.6; color: #475569; margin-top: 10px; padding: 10px 14px; background-color: #f8fafc; border-left: 3px solid #4f46e5; border-radius: 4px;">${joined}</div>`;
            } else {
                previewEl.innerHTML = `<div class="email-signature-single-line" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 13px; line-height: 1.6; color: #475569; margin-top: 10px; padding-top: 10px; border-top: 1px solid #e2e8f0;">${joined}</div>`;
            }
            return;
        }

        // Multi-line Classic layout
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
            lines.push(`<div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">${escapeHtml(address).replace(/\n/g, '<br>')}</div>`);
        }

        previewEl.innerHTML = `<div class="email-signature" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin-top: 10px; padding-top: 10px; border-top: 1px solid #e5e7eb; color: #374151; line-height: 1.4;">${lines.join('')}</div>`;
    }

    async function copySignatureHtml(id) {
        try {
            const container = document.getElementById(`sig-rendered-${id}`);
            let htmlToCopy = '';
            if (container) {
                htmlToCopy = container.innerHTML.trim();
            } else {
                const resp = await fetch(`/signatures/${id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                htmlToCopy = data.rendered_html;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(htmlToCopy);
            } else {
                const ta = document.createElement('textarea');
                ta.value = htmlToCopy;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }
            alert('Signature HTML copied to clipboard!');
        } catch (err) {
            alert('Failed to copy signature HTML: ' + err.message);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
</script>
@endsection
