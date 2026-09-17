@extends('layouts.app')

@section('extra_head')

<!-- Quill Rich Text Editor CSS -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.0-rc.5/dist/quill.snow.css" rel="stylesheet" />
<style>
    .recipient-list-box {
        max-height: 240px;
        overflow-y: auto;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-input);
        background-color: #ffffff;
    }
    /* Quill custom font family dropdown labels */
    .ql-snow .ql-picker.ql-font .ql-picker-label::before,
    .ql-snow .ql-picker.ql-font .ql-picker-item::before {
        content: 'Default' !important;
    }
    .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="abadi"]::before,
    .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="abadi"]::before {
        content: 'Abadi' !important;
    }
    .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="inter"]::before,
    .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="inter"]::before {
        content: 'Inter' !important;
    }
    .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="arial"]::before,
    .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="arial"]::before {
        content: 'Arial' !important;
    }
    .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="courier-new"]::before,
    .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="courier-new"]::before {
        content: 'Courier New' !important;
    }
    .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="georgia"]::before,
    .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="georgia"]::before {
        content: 'Georgia' !important;
    }
    .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="tahoma"]::before,
    .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="tahoma"]::before {
        content: 'Tahoma' !important;
    }
    .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="times-new-roman"]::before,
    .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="times-new-roman"]::before {
        content: 'Times New Roman' !important;
    }
    .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="verdana"]::before,
    .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="verdana"]::before {
        content: 'Verdana' !important;
    }

    /* Quill custom font size dropdown labels */
    .ql-snow .ql-picker.ql-size .ql-picker-label::before,
    .ql-snow .ql-picker.ql-size .ql-picker-item::before {
        content: '14px' !important;
    }
    .ql-snow .ql-picker.ql-size .ql-picker-label[data-value="10px"]::before,
    .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="10px"]::before {
        content: '10px' !important;
    }
    .ql-snow .ql-picker.ql-size .ql-picker-label[data-value="12px"]::before,
    .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="12px"]::before {
        content: '12px' !important;
    }
    .ql-snow .ql-picker.ql-size .ql-picker-label[data-value="14px"]::before,
    .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="14px"]::before {
        content: '14px' !important;
    }
    .ql-snow .ql-picker.ql-size .ql-picker-label[data-value="16px"]::before,
    .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="16px"]::before {
        content: '16px' !important;
    }
    .ql-snow .ql-picker.ql-size .ql-picker-label[data-value="18px"]::before,
    .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="18px"]::before {
        content: '18px' !important;
    }
    .ql-snow .ql-picker.ql-size .ql-picker-label[data-value="20px"]::before,
    .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="20px"]::before {
        content: '20px' !important;
    }
    .ql-snow .ql-picker.ql-size .ql-picker-label[data-value="24px"]::before,
    .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="24px"]::before {
        content: '24px' !important;
    }
    .ql-snow .ql-picker.ql-size .ql-picker-label[data-value="32px"]::before,
    .ql-snow .ql-picker.ql-size .ql-picker-item[data-value="32px"]::before {
        content: '32px' !important;
    }

    .ql-toolbar.ql-snow {
        border: 1px solid var(--border-color) !important;
        border-top-left-radius: var(--radius-input);
        border-top-right-radius: var(--radius-input);
        background-color: #fafafa;
    }
    .ql-container.ql-snow {
        border: 1px solid var(--border-color) !important;
        border-top: none !important;
        border-bottom-left-radius: var(--radius-input);
        border-bottom-right-radius: var(--radius-input);
        background-color: #ffffff;
        font-family: inherit;
    }
    #editor-container {
        height: 250px;
    }
    .attachment-tag {
        background-color: #f4f4f5;
        border: 1px solid var(--border-color);
        padding: 0.35rem 0.65rem;
        border-radius: 6px;
        font-size: 0.775rem;
        font-weight: 500;
        color: #27272a;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s ease;
    }
    .attachment-tag:hover {
        background-color: #e4e4e7;
    }
    .segmented-control {
        background: #f4f4f5;
        padding: 3px;
        border-radius: 8px;
        display: inline-flex;
        gap: 3px;
        border: 1px solid var(--border-color);
    }
    .segmented-control .nav-link {
        border-radius: 6px;
        padding: 0.35rem 0.85rem;
        font-size: 0.8125rem;
        font-weight: 500;
        color: #71717a;
        transition: all 0.15s ease;
        border: 1px solid transparent;
    }
    .segmented-control .nav-link.active {
        background: #ffffff;
        color: #4338ca;
        box-shadow: 0 1px 3px rgba(99, 102, 241, 0.1);
        border: 1px solid rgba(199, 210, 254, 0.7);
        font-weight: 600;
    }
</style>
@endsection

@section('page_title', 'Compose Bulk Campaign')

@section('content')
<div class="row g-4">
    <!-- Salesforce Recipient Selection & Paste (Top) -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-semibold text-zinc-900"><i class="bi bi-people me-1.5 text-indigo-600"></i> Configure Recipients</h6>
                    <small class="text-zinc-500" style="font-size: 0.76rem;">Select CRM contacts or paste a custom list. Compliance and consent checks are applied automatically.</small>
                </div>
                <span class="badge bg-primary-soft text-indigo-700 fw-semibold px-2.5 py-1" id="selected-count">0 Selected</span>
            </div>
            <div class="card-body">
                <!-- Segmented Control Tabs -->
                <div class="mb-3">
                    <ul class="nav segmented-control" id="recipients-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="crm-tab" data-bs-toggle="pill" data-bs-target="#crm-pane" type="button" role="tab" onclick="switchRecipientMethod('crm')">CRM Directory</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="paste-tab" data-bs-toggle="pill" data-bs-target="#paste-pane" type="button" role="tab" onclick="switchRecipientMethod('paste')">Paste Raw List</button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="recipients-tab-content">
                    <!-- Tab 1: CRM Checklist -->
                    <div class="tab-pane fade show active" id="crm-pane" role="tabpanel">
                        
                        <!-- Filters Box -->
                        <div class="p-3 bg-zinc-50 rounded-3 mb-3" style="background-color: #fafafa; border: 1px solid var(--border-color); border-radius: var(--radius-card);">
                            <div class="row g-3">
                                <!-- Type Radio Pill Filter -->
                                <div class="col-12 d-flex align-items-center gap-2 flex-wrap pb-2 border-bottom">
                                    <span class="form-label mb-0 text-zinc-500">Object Type:</span>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="crm-type-filter" id="crm-filter-all" value="all" checked onchange="filterRecipientsType('all')">
                                        <label class="btn btn-outline-secondary btn-xs fw-medium" for="crm-filter-all">All CRM Records</label>

                                        <input type="radio" class="btn-check" name="crm-type-filter" id="crm-filter-lead" value="Lead" onchange="filterRecipientsType('Lead')">
                                        <label class="btn btn-outline-secondary btn-xs fw-medium" for="crm-filter-lead"><i class="bi bi-person me-1"></i>Leads</label>

                                        <input type="radio" class="btn-check" name="crm-type-filter" id="crm-filter-contact" value="Contact" onchange="filterRecipientsType('Contact')">
                                        <label class="btn btn-outline-secondary btn-xs fw-medium" for="crm-filter-contact"><i class="bi bi-person-lines-fill me-1"></i>Contacts</label>

                                        <input type="radio" class="btn-check" name="crm-type-filter" id="crm-filter-account" value="Account" onchange="filterRecipientsType('Account')">
                                        <label class="btn btn-outline-secondary btn-xs fw-medium" for="crm-filter-account"><i class="bi bi-building me-1"></i>Accounts</label>
                                    </div>
                                </div>
                                
                                <!-- 3 Multiselect Picklists (Dynamic from Salesforce) -->
                                <div class="col-12">
                                    <div class="row g-2">
                                        <!-- Category Multiselect -->
                                        <div class="col-md-4">
                                            <label class="form-label mb-1">Deal Category</label>
                                            <div class="dropdown w-100">
                                                <button class="form-select text-start d-flex justify-content-between align-items-center w-100 py-1.5 px-3" type="button" id="btn-filter-category" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.8125rem; font-weight: 500;">
                                                    <span class="text-truncate" id="label-filter-category">All Categories</span>
                                                </button>
                                                <div class="dropdown-menu p-2 shadow-sm border w-100" id="menu-filter-category" aria-labelledby="btn-filter-category" style="max-height: 220px; overflow-y: auto;">
                                                    <div class="text-zinc-400 p-2 text-center" style="font-size: 0.8125rem;">Loading categories...</div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Region Multiselect -->
                                        <div class="col-md-4">
                                            <label class="form-label mb-1">Region</label>
                                            <div class="dropdown w-100">
                                                <button class="form-select text-start d-flex justify-content-between align-items-center w-100 py-1.5 px-3" type="button" id="btn-filter-region" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.8125rem; font-weight: 500;">
                                                    <span class="text-truncate" id="label-filter-region">All Regions</span>
                                                </button>
                                                <div class="dropdown-menu p-2 shadow-sm border w-100" id="menu-filter-region" aria-labelledby="btn-filter-region" style="max-height: 220px; overflow-y: auto;">
                                                    <div class="text-zinc-400 p-2 text-center" style="font-size: 0.8125rem;">Loading regions...</div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Country Multiselect -->
                                        <div class="col-md-4">
                                            <label class="form-label mb-1">Country</label>
                                            <div class="dropdown w-100">
                                                <button class="form-select text-start d-flex justify-content-between align-items-center w-100 py-1.5 px-3" type="button" id="btn-filter-country" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.8125rem; font-weight: 500;">
                                                    <span class="text-truncate" id="label-filter-country">All Countries</span>
                                                </button>
                                                <div class="dropdown-menu p-2 shadow-sm border w-100" id="menu-filter-country" aria-labelledby="btn-filter-country" style="max-height: 220px; overflow-y: auto;">
                                                    <div class="text-zinc-400 p-2 text-center" style="font-size: 0.8125rem;">Loading countries...</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Search & Quick Selection Bar -->
                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-md-8 col-sm-7">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white border-end-0 text-zinc-400"><i class="bi bi-search"></i></span>
                                    <input type="text" id="rec-search" class="form-control border-start-0 ps-0" placeholder="Search contacts by name or ID..." onkeyup="filterRecipients()">
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-5 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="toggleSelectAll(true)"><i class="bi bi-check-all"></i> Select All</button>
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="toggleSelectAll(false)"><i class="bi bi-x"></i> Clear</button>
                            </div>
                        </div>

                        <!-- Recipient Checklist -->
                        <div class="recipient-list-box mb-1">
                            <ul class="list-group list-group-flush" id="recipient-list">
                                <li class="list-group-item text-center text-muted py-4">Loading Salesforce records...</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Tab 2: Paste Email List -->
                    <div class="tab-pane fade" id="paste-pane" role="tabpanel">
                        <p class="text-zinc-500 mb-2" style="font-size: 0.78rem;">Paste email addresses line-by-line. Opt-out and compliance records will be verified automatically.</p>
                        <div>
                            <textarea class="form-control font-monospace" id="pasted-emails" rows="6" placeholder="john.doe@company.com&#10;jane.smith@partner.net&#10;david@leads.com" style="font-size: 0.82rem;"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Message Details & Attachments (Bottom) -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold text-zinc-900"><i class="bi bi-envelope-open me-1.5 text-zinc-500"></i> Message Content & Configuration</h6>
            </div>
            <div class="card-body">
                <form id="campaign-form" onsubmit="event.preventDefault();">
                    <!-- Templates Selector & Save row -->
                    <div class="row g-2 mb-3 align-items-end">
                        <div class="col-md-8">
                            <label for="template-select" class="form-label mb-1">Load Saved Template</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-zinc-400 border-end-0"><i class="bi bi-journal-bookmark"></i></span>
                                <select id="template-select" class="form-select border-start-0" onchange="loadSelectedTemplate()">
                                    <option value="">-- No Template Selected --</option>
                                </select>
                                <button type="button" class="btn btn-outline-danger" onclick="deleteSelectedTemplate()" title="Delete Template">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-secondary w-100" onclick="saveAsTemplate()">
                                <i class="bi bi-bookmark-plus"></i> Save as Template
                            </button>
                        </div>
                    </div>

                    <!-- Subject & Attachments Row -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="subject" class="form-label mb-1">Subject Line</label>
                            <input type="text" class="form-control" id="subject" placeholder="e.g. Strategic Partnership Discussion" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1"><i class="bi bi-paperclip me-1"></i> Attachments</label>
                            <input type="file" id="attachments-input" class="form-control" multiple onchange="handleFileSelect()">
                            <div id="attachments-list" class="mt-2 d-flex flex-wrap gap-1.5"></div>
                        </div>
                    </div>

                    <!-- Sender Info Row (Visible only to Admin) -->
                    <div class="row g-3 mb-3 {{ (session('role', Auth::user()->role ?? '') !== 'admin') ? 'd-none' : '' }}">
                        <div class="col-md-6">
                            <label for="sending-domain" class="form-label mb-1">Outbound Domain</label>
                            <select class="form-select" id="sending-domain" onchange="updateFromEmail()" required>
                                <!-- Populated dynamically -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="from-address" class="form-label mb-1">From Address</label>
                            <input type="email" class="form-control" id="from-address" placeholder="rma@proitbuyer.com" value="rma@proitbuyer.com" required>
                        </div>
                    </div>

                    <!-- Rich HTML Editor -->
                    <div class="mb-3">
                        <label class="form-label mb-1">Email Body Content</label>
                        <div id="editor-wrapper">
                            <div id="editor-container"></div>
                        </div>
                        <div class="form-text text-zinc-500" style="font-size: 0.74rem;">Merge tokens: <code>@{{FirstName}}</code>, <code>@{{LastName}}</code> map to recipient CRM fields.</div>
                    </div>

                    <!-- Signatures & Schedule Grid -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="signature-select" class="form-label mb-1"><i class="bi bi-pen me-1"></i> Insert Signature</label>
                            <div class="d-flex gap-1.5">
                                <select id="signature-select" class="form-select" onchange="insertSelectedSignature()">
                                    <option value="">-- Select a Signature --</option>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" onclick="saveCurrentAsSignature()" title="Save Signature">
                                    <i class="bi bi-plus"></i> Save
                                </button>
                                <button type="button" class="btn btn-outline-danger" onclick="deleteSelectedSignature()" title="Delete Signature">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="schedule-datetime" class="form-label mb-1"><i class="bi bi-clock me-1"></i> Schedule Send (Atlanta EST/EDT)</label>
                            <div class="input-group">
                                <input type="datetime-local" id="schedule-datetime" class="form-control">
                                <button class="btn btn-outline-secondary" type="button" onclick="showTimezoneChecker()"><i class="bi bi-globe"></i> Timezones</button>
                            </div>
                        </div>
                    </div>

                    <!-- Action bar -->
                    <div class="d-flex justify-content-end pt-3 border-top">
                        <button type="button" class="btn btn-primary" onclick="runPreSendValidation()">
                            <i class="bi bi-shield-check"></i> Verify & Dispatch
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Pre-Send Validation Summary Panel -->
    <div class="col-12 d-none" id="validation-summary-card">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-zinc-900"><i class="bi bi-shield-lock me-1.5"></i> Pre-Send Compliance Summary</span>
                <span class="badge bg-secondary-soft" id="val-badge-total">0 Deliverable</span>
            </div>
            <div class="card-body">
                <div class="row g-2 text-center mb-3">
                    <div class="col">
                        <div class="p-2.5 rounded-3 bg-zinc-50 border">
                            <span class="stat-label d-block">Selected</span>
                            <span class="fs-6 fw-bold text-zinc-900" id="sum-selected">0</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2.5 rounded-3 bg-success-soft border border-success-subtle">
                            <span class="stat-label d-block text-emerald-800">Eligible</span>
                            <span class="fs-6 fw-bold text-emerald-700" id="sum-approved">0</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2.5 rounded-3 bg-danger-soft border border-danger-subtle">
                            <span class="stat-label d-block text-rose-800">Blocked</span>
                            <span class="fs-6 fw-bold text-rose-700" id="sum-blocked">0</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2.5 rounded-3 bg-warning-soft border border-warning-subtle">
                            <span class="stat-label d-block text-amber-800">Diff Owner</span>
                            <span class="fs-6 fw-bold text-amber-700" id="sum-owner-blocked">0</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2.5 rounded-3 bg-warning-soft border border-warning-subtle">
                            <span class="stat-label d-block text-amber-800">Opt-Out</span>
                            <span class="fs-6 fw-bold text-amber-700" id="sum-optout-blocked">0</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2.5 rounded-3 bg-warning-soft border border-warning-subtle">
                            <span class="stat-label d-block text-amber-800">Suppressed</span>
                            <span class="fs-6 fw-bold text-amber-700" id="sum-spam-blocked">0</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2.5 rounded-3 bg-secondary-soft border">
                            <span class="stat-label d-block">Duplicates</span>
                            <span class="fs-6 fw-bold text-zinc-700" id="sum-duplicates">0</span>
                        </div>
                    </div>
                </div>

                <!-- Blocked Details breakdowns -->
                <div class="accordion mb-3" id="blockedDetailsAccordion">
                    <div class="accordion-item border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed fw-medium text-rose-700 bg-rose-soft py-2 px-3 rounded-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBlockedList" style="font-size: 0.8rem;">
                                View Blocked Records Breakdown
                            </button>
                        </h2>
                        <div id="collapseBlockedList" class="accordion-collapse collapse" data-bs-parent="#blockedDetailsAccordion">
                            <div class="accordion-body px-0 py-2">
                                <ul class="list-group list-group-flush" id="validation-error-list" style="max-height: 250px; overflow-y: auto;">
                                    <!-- Populated dynamically -->
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Final send actions -->
                <div class="d-flex justify-content-end gap-2 align-items-center pt-2.5 border-top">
                    <button class="btn btn-outline-secondary btn-sm" onclick="hideValidationSummary()">Cancel</button>
                    <button class="btn btn-primary btn-sm" id="btn-submit-send" onclick="submitCampaign()">
                        <i class="bi bi-send-fill"></i> Confirm Dispatch
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Timezone Checker Modal -->
<div class="modal fade" id="timezoneModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-3 px-4" style="border-bottom: 1px solid var(--border-color);">
                <h6 class="modal-title fw-semibold text-zinc-900"><i class="bi bi-globe me-1.5"></i> Timezone Conversion Matrix</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.75rem;"></button>
            </div>
            <div class="modal-body py-3 px-4">
                <p class="text-zinc-500 mb-3" style="font-size: 0.78rem;">Converted from scheduled Atlanta time: <strong class="text-zinc-900" id="tz-atlanta-input-label">N/A</strong></p>
                <div class="table-responsive border-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Target Region</th>
                                <th>Local Time</th>
                            </tr>
                        </thead>
                        <tbody id="timezone-conversion-table-body">
                            <!-- Loaded dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('extra_scripts')
<!-- Quill Editor JS -->
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.0-rc.5/dist/quill.js"></script>

<script>
    let quill = null;
    let selectedRecords = new Set();
    let allRecords = [];
    let attachedFilesList = [];
    let recipientMethod = 'crm'; // 'crm' or 'paste'

    // Configure custom fonts and sizes in Quill
    const Size = Quill.import('attributors/style/size');
    Size.whitelist = ['10px', '12px', '14px', '16px', '18px', '20px', '24px', '32px'];
    Quill.register(Size, true);

    const Font = Quill.import('attributors/style/font');
    Font.whitelist = ['abadi', 'inter', 'arial', 'courier-new', 'georgia', 'tahoma', 'times-new-roman', 'verdana', 'sans-serif', 'serif', 'monospace'];
    Quill.register(Font, true);

    // Initialize Rich text
    quill = new Quill('#editor-container', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'font': ['abadi', 'inter', 'arial', 'courier-new', 'georgia', 'tahoma', 'times-new-roman', 'verdana'] }],
                [{ 'size': ['10px', '12px', '14px', '16px', '18px', '20px', '24px', '32px'] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link', 'clean']
            ]
        }
    });
    
    quill.root.innerHTML = `<p>Hi @{{FirstName}},</p><p>We wanted to check in and share some exciting updates.</p><p>Best regards,<br>Team</p>`;

    function handleFileSelect() {
        const input = document.getElementById('attachments-input');
        const listDiv = document.getElementById('attachments-list');
        
        for (let i = 0; i < input.files.length; i++) {
            const file = input.files[i];
            // Prevent duplicate attachments listing
            if (!attachedFilesList.includes(file.name)) {
                attachedFilesList.push(file.name);
            }
        }
        
        renderAttachments();
    }

    function renderAttachments() {
        const listDiv = document.getElementById('attachments-list');
        let html = '';
        attachedFilesList.forEach((filename, index) => {
            html += `
                <div class="attachment-tag">
                    <i class="bi bi-file-earmark-arrow-down-fill text-secondary"></i>
                    <span>${escapeHtml(filename)}</span>
                    <i class="bi bi-x-circle text-danger ms-1 cursor-pointer" onclick="removeAttachment(${index})" style="cursor: pointer;"></i>
                </div>
            `;
        });
        listDiv.innerHTML = html;
    }

    function removeAttachment(index) {
        attachedFilesList.splice(index, 1);
        renderAttachments();
    }

    function switchRecipientMethod(method) {
        recipientMethod = method;
        if (method === 'paste') {
            document.getElementById('selected-count').innerText = "Paste List Mode";
        } else {
            document.getElementById('selected-count').innerText = `${selectedRecords.size} Selected`;
        }
    }

    function loadSendingDomains() {
        fetch('/api/admin/domains')
            .then(res => res.json())
            .then(domains => {
                const select = document.getElementById('sending-domain');
                if (!select) return;
                const activeDomains = Array.isArray(domains) ? domains.filter(d => d.status === 'enabled') : [];
                
                if (activeDomains.length === 0) {
                    select.innerHTML = '<option value="proitbuyer.com" selected>proitbuyer.com (Default)</option>';
                    updateFromEmail();
                    return;
                }

                fetch('/api/user/assigned-settings')
                    .then(res2 => res2.json())
                    .then(userSettings => {
                        const assignedDomain = userSettings.domain_name;
                        let html = '';
                        activeDomains.forEach(d => {
                            let isSelected = '';
                            if (assignedDomain) {
                                isSelected = d.domain_name === assignedDomain ? 'selected' : '';
                            } else {
                                isSelected = d.is_default === 1 ? 'selected' : '';
                            }
                            html += `<option value="${escapeHtml(d.domain_name)}" ${isSelected}>${escapeHtml(d.domain_name)}</option>`;
                        });
                        select.innerHTML = html;
                        updateFromEmail();
                    })
                    .catch(() => {
                        let html = '';
                        activeDomains.forEach(d => {
                            const selected = d.is_default === 1 ? 'selected' : '';
                            html += `<option value="${escapeHtml(d.domain_name)}" ${selected}>${escapeHtml(d.domain_name)}</option>`;
                        });
                        select.innerHTML = html;
                        updateFromEmail();
                    });
            })
            .catch(() => {
                const select = document.getElementById('sending-domain');
                if (select) {
                    select.innerHTML = '<option value="proitbuyer.com" selected>proitbuyer.com (Default)</option>';
                    updateFromEmail();
                }
            });
    }

    function updateFromEmail() {
        const fromInput = document.getElementById('from-address');
        if (fromInput) {
            fromInput.value = 'rma@proitbuyer.com';
        }
    }

    function loadRecipients() {
        fetch('/api/salesforce/recipients')
            .then(res => res.json())
            .then(records => {
                allRecords = records || [];
                populateFilterPicklists(allRecords);
                renderRecipients(allRecords);
            })
            .catch(err => console.error("Error loading recipients:", err));
    }

    function populateFilterPicklists(records) {
        // Keep track of currently checked values
        const prevCategories = new Set(Array.from(document.querySelectorAll('.filter-category-checkbox:checked')).map(cb => cb.value));
        const prevRegions = new Set(Array.from(document.querySelectorAll('.filter-region-checkbox:checked')).map(cb => cb.value));
        const prevCountries = new Set(Array.from(document.querySelectorAll('.filter-country-checkbox:checked')).map(cb => cb.value));

        // 1. Categories
        const categories = Array.from(new Set(records.map(r => r.deal_category).filter(Boolean))).sort();
        const catMenu = document.getElementById('menu-filter-category');
        if (catMenu) {
            let catHtml = '';
            categories.forEach((cat, idx) => {
                const isChecked = prevCategories.has(cat) ? 'checked' : '';
                catHtml += `
                    <div class="form-check py-1 px-3 d-flex align-items-center gap-2 rounded-2">
                        <input class="form-check-input filter-category-checkbox mt-0" type="checkbox" value="${escapeHtml(cat)}" id="cat-${idx}" ${isChecked} onchange="filterRecipients()">
                        <label class="form-check-label text-zinc-800" for="cat-${idx}" style="font-size: 0.8125rem; font-weight: 500; cursor: pointer;">${escapeHtml(cat)}</label>
                    </div>
                `;
            });
            if (!catHtml) catHtml = '<div class="text-zinc-400 p-2 text-center" style="font-size: 0.8125rem;">No categories in Salesforce</div>';
            catMenu.innerHTML = catHtml;
        }

        // 2. Regions
        const regions = Array.from(new Set(records.map(r => r.region).filter(Boolean))).sort();
        const regMenu = document.getElementById('menu-filter-region');
        if (regMenu) {
            let regHtml = '';
            regions.forEach((reg, idx) => {
                const isChecked = prevRegions.has(reg) ? 'checked' : '';
                regHtml += `
                    <div class="form-check py-1 px-3 d-flex align-items-center gap-2 rounded-2">
                        <input class="form-check-input filter-region-checkbox mt-0" type="checkbox" value="${escapeHtml(reg)}" id="reg-${idx}" ${isChecked} onchange="filterRecipients()">
                        <label class="form-check-label text-zinc-800" for="reg-${idx}" style="font-size: 0.8125rem; font-weight: 500; cursor: pointer;">${escapeHtml(reg)}</label>
                    </div>
                `;
            });
            if (!regHtml) regHtml = '<div class="text-zinc-400 p-2 text-center" style="font-size: 0.8125rem;">No regions in Salesforce</div>';
            regMenu.innerHTML = regHtml;
        }

        // 3. Countries
        const countries = Array.from(new Set(records.map(r => r.country).filter(Boolean))).sort();
        const ctyMenu = document.getElementById('menu-filter-country');
        if (ctyMenu) {
            let ctyHtml = '';
            countries.forEach((cty, idx) => {
                const isChecked = prevCountries.has(cty) ? 'checked' : '';
                ctyHtml += `
                    <div class="form-check py-1 px-3 d-flex align-items-center gap-2 rounded-2">
                        <input class="form-check-input filter-country-checkbox mt-0" type="checkbox" value="${escapeHtml(cty)}" id="cty-${idx}" ${isChecked} onchange="filterRecipients()">
                        <label class="form-check-label text-zinc-800" for="cty-${idx}" style="font-size: 0.8125rem; font-weight: 500; cursor: pointer;">${escapeHtml(cty)}</label>
                    </div>
                `;
            });
            if (!ctyHtml) ctyHtml = '<div class="text-zinc-400 p-2 text-center" style="font-size: 0.8125rem;">No countries in Salesforce</div>';
            ctyMenu.innerHTML = ctyHtml;
        }
    }

    let currentCrmTypeFilter = 'all';

    function filterRecipientsType(type) {
        currentCrmTypeFilter = type;
        filterRecipients();
    }

    function renderRecipients(records) {
        const list = document.getElementById('recipient-list');
        
        let filtered = records;
        if (currentCrmTypeFilter !== 'all') {
            filtered = records.filter(r => r.object_type === currentCrmTypeFilter);
        }

        // Deal Category Picklist filter
        const activeCategories = Array.from(document.querySelectorAll('.filter-category-checkbox:checked')).map(cb => cb.value);
        if (activeCategories.length > 0) {
            filtered = filtered.filter(r => activeCategories.includes(r.deal_category));
            document.getElementById('label-filter-category').innerText = activeCategories.length <= 2 
                ? activeCategories.join(', ') 
                : `${activeCategories.length} selected`;
        } else {
            document.getElementById('label-filter-category').innerText = 'All Categories';
        }

        // Region Picklist filter
        const activeRegions = Array.from(document.querySelectorAll('.filter-region-checkbox:checked')).map(cb => cb.value);
        if (activeRegions.length > 0) {
            filtered = filtered.filter(r => activeRegions.includes(r.region));
            document.getElementById('label-filter-region').innerText = activeRegions.length <= 2 
                ? activeRegions.join(', ') 
                : `${activeRegions.length} selected`;
        } else {
            document.getElementById('label-filter-region').innerText = 'All Regions';
        }

        // Country Picklist filter
        const activeCountries = Array.from(document.querySelectorAll('.filter-country-checkbox:checked')).map(cb => cb.value);
        if (activeCountries.length > 0) {
            filtered = filtered.filter(r => activeCountries.includes(r.country));
            document.getElementById('label-filter-country').innerText = activeCountries.length <= 2 
                ? activeCountries.join(', ') 
                : `${activeCountries.length} selected`;
        } else {
            document.getElementById('label-filter-country').innerText = 'All Countries';
        }

        if (filtered.length === 0) {
            list.innerHTML = '<li class="list-group-item text-center text-muted py-4" style="font-size: 0.8125rem;">No matching Salesforce records accessible.</li>';
            return;
        }

        const showEmail = currentCrmTypeFilter !== 'all';
        let html = '';
        filtered.forEach(r => {
            const isChecked = selectedRecords.has(r.id) ? 'checked' : '';
            let typeBadge = '<span class="badge bg-warning-soft text-amber-800 me-1" style="font-size: 0.65rem;">Lead</span>';
            if (r.object_type === 'Contact') {
                typeBadge = '<span class="badge bg-secondary-soft text-zinc-700 me-1" style="font-size: 0.65rem;">Contact</span>';
            } else if (r.object_type === 'Account') {
                typeBadge = '<span class="badge bg-primary-soft text-indigo-700 me-1" style="font-size: 0.65rem;">Account</span>';
            }
            
            let vBadge = '';
            if (r.owner_verification_status === 'verified') {
                vBadge = '<span class="badge bg-success-soft ms-1" style="font-size: 0.65rem;" title="Salesforce Owner Verified"><i class="bi bi-shield-check"></i> Verified</span>';
            } else if (r.owner_verification_status === 'changed') {
                vBadge = '<span class="badge bg-warning-soft text-amber-800 ms-1" style="font-size: 0.65rem;" title="Salesforce Owner Changed"><i class="bi bi-arrow-repeat"></i> Owner Changed</span>';
            }

            let warning = '';
            if (r.opted_out === 1 || r.opted_out === true) warning += ' <i class="bi bi-slash-circle text-rose-600 ms-1" title="Opted Out in Salesforce"></i>';
            if (r.consent_status !== 'valid') warning += ' <i class="bi bi-exclamation-triangle text-amber-600 ms-1" title="GDPR Non-compliant"></i>';

            const emailText = showEmail ? ` &bull; <span class="text-indigo-600 font-monospace" style="font-size: 0.775rem;">${escapeHtml(r.email)}</span>` : '';
            const companyText = r.company ? ` &bull; <span class="text-zinc-600">${escapeHtml(r.company)}</span>` : '';
            const ownerText = r.owner_name ? ` &bull; <span class="text-zinc-500">Owner: ${escapeHtml(r.owner_name)}</span>` : '';
            const detailsText = `<div class="text-zinc-500 mt-0.5" style="font-size: 0.74rem;">Category: <span class="text-zinc-800 fw-medium">${escapeHtml(r.deal_category || 'None')}</span> &bull; Region: <span class="text-zinc-800 fw-medium">${escapeHtml(r.region || 'None')}</span> &bull; Country: <span class="text-zinc-800 fw-medium">${escapeHtml(r.country || 'None')}</span>${ownerText}</div>`;

            html += `
                <li class="list-group-item d-flex align-items-center py-2.5 px-3 recipient-row" data-id="${r.id}">
                    <input class="form-check-input me-3 rec-checkbox mt-0" type="checkbox" value="${r.id}" ${isChecked} onchange="handleSelect(this)">
                    <div style="font-size: 0.8125rem;" class="flex-grow-1">
                        <div class="fw-semibold text-zinc-900">${typeBadge}${escapeHtml(r.name || (r.first_name + ' ' + r.last_name))}${companyText}${emailText}${vBadge}${warning}</div>
                        ${detailsText}
                        <div class="text-zinc-400 font-monospace" style="font-size: 0.71rem;">SF ID: ${r.id}</div>
                    </div>
                </li>
            `;
        });
        list.innerHTML = html;
    }

    function filterRecipients() {
        renderRecipients(allRecords);
        const query = document.getElementById('rec-search').value.toLowerCase();
        if (query) {
            const rows = document.querySelectorAll('.recipient-row');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                if (text.includes(query)) {
                    row.classList.remove('d-none');
                } else {
                    row.classList.add('d-none');
                }
            });
        }
    }

    function toggleSelectAll(select) {
        const checkboxes = document.querySelectorAll('.rec-checkbox:not(.d-none)');
        checkboxes.forEach(cb => {
            cb.checked = select;
            handleSelect(cb);
        });
    }

    function handleSelect(cb) {
        if (cb.checked) {
            selectedRecords.add(cb.value);
        } else {
            selectedRecords.delete(cb.value);
        }
        if (recipientMethod === 'crm') {
            document.getElementById('selected-count').innerText = `${selectedRecords.size} Selected`;
        }
    }

    function runPreSendValidation() {
        let payload = {};

        if (recipientMethod === 'crm') {
            if (selectedRecords.size === 0) {
                alert("Please select at least one Salesforce recipient first.");
                return;
            }
            payload.recipient_ids = Array.from(selectedRecords);
        } else {
            const pastedText = document.getElementById('pasted-emails').value;
            const emails = pastedText.split(/[\n,]+/).map(e => e.trim()).filter(e => e.length > 0);
            if (emails.length === 0) {
                alert("Please paste email addresses first.");
                return;
            }
            payload.recipient_emails = emails;
        }

        fetch('/api/campaign/validate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('sum-selected').innerText = data.total_selected;
            document.getElementById('sum-approved').innerText = data.total_approved;
            document.getElementById('sum-blocked').innerText = data.total_blocked;
            document.getElementById('sum-duplicates').innerText = data.duplicates.length;

            const diffOwnerCount = (data.blocked_by_reason["DIFFERENT_OWNER"] || []).length;
            const optOutCount = (data.blocked_by_reason["EMAIL_OPT_OUT"] || []).length;
            const spamCount = (data.blocked_by_reason["GLOBAL_SUPPRESSION"] || []).length;

            document.getElementById('sum-owner-blocked').innerText = diffOwnerCount;
            document.getElementById('sum-optout-blocked').innerText = optOutCount;
            document.getElementById('sum-spam-blocked').innerText = spamCount;

            document.getElementById('val-badge-total').innerText = `${data.total_approved} Deliverable`;

            const errList = document.getElementById('validation-error-list');
            let errHtml = '';

            data.duplicates.forEach(d => {
                errHtml += `
                    <li class="list-group-item d-flex justify-content-between align-items-center py-1.5 px-3 text-danger border-light-subtle" style="font-size: 0.78rem;">
                        <span><span class="badge bg-secondary-soft me-1">${escapeHtml(d.record_type || 'CRM')}</span><strong>${escapeHtml(d.name)}</strong> (${escapeHtml(d.email)})</span>
                        <span class="badge bg-danger-soft text-danger fw-semibold" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">Duplicate Entry</span>
                    </li>
                `;
            });

            const reasonLabels = {
                "EMAIL_OPT_OUT": "Salesforce Email Opt-Out (HasOptedOutOfEmail)",
                "GLOBAL_SUPPRESSION": "Global Suppression List",
                "DIFFERENT_OWNER": "Salesforce Ownership Check Failed (Wrong Owner)",
                "INVALID_EMAIL": "Not found in CRM / Invalid Email",
                "INACTIVE_RECORD": "Inactive Record Status",
                "MISSING_CONSENT": "GDPR Compliance Check Failed"
            };

            for (const [reason, list] of Object.entries(data.blocked_by_reason)) {
                list.forEach(r => {
                    const typeLabel = r.record_type ? `<span class="badge bg-secondary-soft me-1">${escapeHtml(r.record_type)}</span>` : '';
                    const vStatusLabel = r.owner_verification_status ? `<span class="badge bg-light text-muted ms-1">${escapeHtml(r.owner_verification_status)}</span>` : '';
                    errHtml += `
                        <li class="list-group-item d-flex justify-content-between align-items-center py-1.5 px-3 text-danger border-light-subtle" style="font-size: 0.78rem;">
                            <span>${typeLabel}<strong>${escapeHtml(r.name)}</strong> (${escapeHtml(r.email)})${vStatusLabel}</span>
                            <span class="badge bg-danger-soft text-danger fw-semibold" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">${reasonLabels[reason] || reason}</span>
                        </li>
                    `;
                });
            }

            if (errHtml === '') {
                errHtml = '<li class="list-group-item text-center text-success py-2">All recipients are fully compliant and authorized! No blocks.</li>';
            }

            errList.innerHTML = errHtml;

            const btnSubmit = document.getElementById('btn-submit-send');
            if (data.total_approved === 0) {
                btnSubmit.disabled = true;
                btnSubmit.className = 'btn btn-secondary';
            } else {
                btnSubmit.disabled = false;
                btnSubmit.className = 'btn btn-primary';
            }

            const valCard = document.getElementById('validation-summary-card');
            valCard.classList.remove('d-none');
            valCard.scrollIntoView({ behavior: 'smooth' });
        })
        .catch(err => console.error("Validation error:", err));
    }

    function hideValidationSummary() {
        document.getElementById('validation-summary-card').classList.add('d-none');
    }

    function submitCampaign() {
        const subject = document.getElementById('subject').value;
        const sendingDomainEl = document.getElementById('sending-domain');
        const sending_domain = (sendingDomainEl && sendingDomainEl.value) ? sendingDomainEl.value : 'proitbuyer.com';
        const from_address = 'rma@proitbuyer.com';
        const body = quill.root.innerHTML;
        const scheduled_at = document.getElementById('schedule-datetime').value || null;

        if (!subject || !body) {
            alert("Please provide both subject and body for the email.");
            return;
        }

        const payload = {
            subject,
            sending_domain,
            from_address,
            body,
            attachments: attachedFilesList,
            scheduled_at: scheduled_at
        };

        if (recipientMethod === 'crm') {
            const recipient_ids = Array.from(selectedRecords);
            if (recipient_ids.length === 0) {
                alert("Please select recipients.");
                return;
            }
            payload.recipient_ids = recipient_ids;
        } else {
            const pastedText = document.getElementById('pasted-emails').value;
            const emails = pastedText.split(/[\n,]+/).map(e => e.trim()).filter(e => e.length > 0);
            if (emails.length === 0) {
                alert("Please paste email addresses.");
                return;
            }
            payload.recipient_emails = emails;
        }

        fetch('/api/campaign/send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.href = `/campaign/${data.campaign_id}`;
            } else {
                alert(data.error || "Failed to submit campaign.");
            }
        })
        .catch(err => console.error("Error creating campaign:", err));
    }

    let savedTemplates = [];
    let savedSignatures = [];

    function loadTemplates() {
        fetch('/api/templates')
            .then(res => res.json())
            .then(data => {
                savedTemplates = data;
                const select = document.getElementById('template-select');
                select.innerHTML = '<option value="">-- No Template Selected --</option>';
                data.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = t.name;
                    select.appendChild(opt);
                });
            });
    }

    function saveAsTemplate() {
        const name = prompt("Enter a name for this template:");
        if (!name) return;
        const subject = document.getElementById('subject').value;
        const body = quill.root.innerHTML;

        fetch('/api/templates', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, subject, body })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Template saved successfully!");
                loadTemplates();
            } else {
                alert(data.error || "Failed to save template.");
            }
        });
    }

    function loadSelectedTemplate() {
        const select = document.getElementById('template-select');
        const id = select.value;
        if (!id) return;
        const tmpl = savedTemplates.find(t => t.id == id);
        if (tmpl) {
            document.getElementById('subject').value = tmpl.subject;
            quill.root.innerHTML = tmpl.body;
        }
    }

    function loadSignatures() {
        fetch('/api/signatures')
            .then(res => res.json())
            .then(data => {
                savedSignatures = data;
                const select = document.getElementById('signature-select');
                select.innerHTML = '<option value="">-- Select a Signature --</option>';
                data.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.name;
                    select.appendChild(opt);
                });
            });
    }

    function saveCurrentAsSignature() {
        const name = prompt("Enter a name for this signature:");
        if (!name) return;
        const content = quill.root.innerHTML;

        fetch('/api/signatures', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, content })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Signature saved successfully!");
                loadSignatures();
            } else {
                alert(data.error || "Failed to save signature.");
            }
        });
    }

    function insertSelectedSignature() {
        const select = document.getElementById('signature-select');
        const id = select.value;
        if (!id) return;
        const sig = savedSignatures.find(s => s.id == id);
        if (sig) {
            quill.root.innerHTML += `<br><br>${sig.content}`;
        }
    }

    function deleteSelectedTemplate() {
        const select = document.getElementById('template-select');
        const id = select.value;
        if (!id) {
            alert("Please select a template to delete.");
            return;
        }
        if (!confirm("Are you sure you want to permanently delete this template?")) {
            return;
        }
        fetch(`/api/templates/${id}`, { method: 'DELETE' })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("Template deleted successfully!");
                    loadTemplates();
                } else {
                    alert(data.error || "Failed to delete template.");
                }
            })
            .catch(err => console.error("Error deleting template:", err));
    }

    function deleteSelectedSignature() {
        const select = document.getElementById('signature-select');
        const id = select.value;
        if (!id) {
            alert("Please select a signature to delete.");
            return;
        }
        if (!confirm("Are you sure you want to permanently delete this signature?")) {
            return;
        }
        fetch(`/api/signatures/${id}`, { method: 'DELETE' })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("Signature deleted successfully!");
                    loadSignatures();
                } else {
                    alert(data.error || "Failed to delete signature.");
                }
            })
            .catch(err => console.error("Error deleting signature:", err));
    }

    const timezones = [
        { name: "Atlanta (Georgia, USA) - EST/EDT", tz: "America/New_York" },
        { name: "San Francisco (California, USA) - PST/PDT", tz: "America/Los_Angeles" },
        { name: "London (United Kingdom) - GMT/BST", tz: "Europe/London" },
        { name: "Frankfurt (Germany) - CET/CEST", tz: "Europe/Berlin" },
        { name: "New Delhi (India) - IST", tz: "Asia/Kolkata" },
        { name: "Tokyo (Japan) - JST", tz: "Asia/Tokyo" },
        { name: "Sydney (Australia) - AEST/AEDT", tz: "Australia/Sydney" }
    ];

    function getAtlantaDate(datetimeStr) {
        const parts = datetimeStr.split('T');
        const dateParts = parts[0].split('-');
        const timeParts = parts[1].split(':');
        
        const year = parseInt(dateParts[0], 10);
        const month = parseInt(dateParts[1], 10) - 1;
        const day = parseInt(dateParts[2], 10);
        const hour = parseInt(timeParts[0], 10);
        const minute = parseInt(timeParts[1], 10);
        
        const formatter = new Intl.DateTimeFormat('en-US', {
            timeZone: 'America/New_York',
            year: 'numeric', month: 'numeric', day: 'numeric',
            hour: 'numeric', minute: 'numeric', second: 'numeric',
            hour12: false
        });
        
        for (const offset of [-4, -5]) {
            const candidate = new Date(Date.UTC(year, month, day, hour - offset, minute));
            const formatted = formatter.format(candidate);
            if (formatted.includes(`${hour}:${minute}`) || formatted.includes(` ${hour}:${minute}`)) {
                return candidate;
            }
        }
        return new Date(year, month, day, hour, minute);
    }

    function showTimezoneChecker() {
        const datetimeInput = document.getElementById('schedule-datetime').value;
        if (!datetimeInput) {
            alert("Please select a scheduled date and time first.");
            return;
        }

        const utcDate = getAtlantaDate(datetimeInput);
        
        document.getElementById('tz-atlanta-input-label').innerText = new Date(utcDate).toLocaleString('en-US', {
            timeZone: 'America/New_York',
            dateStyle: 'medium',
            timeStyle: 'short'
        }) + " (Atlanta Time)";

        const tbody = document.getElementById('timezone-conversion-table-body');
        let html = '';
        timezones.forEach(tzInfo => {
            const timeStr = utcDate.toLocaleString('en-US', {
                timeZone: tzInfo.tz,
                dateStyle: 'medium',
                timeStyle: 'short'
            });
            html += `
                <tr>
                    <td class="fw-semibold text-slate-800">${escapeHtml(tzInfo.name)}</td>
                    <td class="text-secondary">${escapeHtml(timeStr)}</td>
                </tr>
            `;
        });
        tbody.innerHTML = html;

        const tzModal = new bootstrap.Modal(document.getElementById('timezoneModal'));
        tzModal.show();
    }

    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    window.onload = function() {
        loadSendingDomains();
        loadRecipients();
        loadTemplates();
        loadSignatures();

        // Prevent closing of bootstrap dropdowns when clicking checkboxes inside them
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        });
    };
</script>
@endsection
