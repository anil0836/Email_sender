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

    .btn-xs {
        padding: 0.2rem 0.5rem;
        font-size: 0.725rem;
        line-height: 1.25;
        border-radius: 4px;
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

@php
$isPasteMode = ($mode ?? request('mode', '')) === 'paste' || request()->routeIs('campaign_bulk_view');
@endphp

@section('page_title', $isPasteMode ? 'Bulk Email (Raw List)' : 'Compose Bulk Campaign')

@section('content')
<div class="row g-4">
    <!-- Salesforce Recipient Selection & Paste (Top) -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-semibold text-zinc-900">
                        <i
                            class="bi {{ $isPasteMode ? 'bi-envelope-at text-indigo-600' : 'bi-people text-indigo-600' }} me-1.5"></i>
                        {{ $isPasteMode ? 'Bulk Email Recipients (Paste List or Upload CSV)' : 'Configure Recipients' }}
                    </h6>
                    <small class="text-zinc-500" style="font-size: 0.76rem;">
                        {{ $isPasteMode ? 'Upload a CSV file or paste email addresses directly. Opt-out, suppression, and compliance records will be verified automatically.' : 'Select CRM contacts or paste a custom list. Compliance and consent checks are applied automatically.' }}
                    </small>
                </div>
                <span class="badge bg-primary-soft text-indigo-700 fw-semibold px-2.5 py-1" id="selected-count">{{
                    $isPasteMode ? '0 Emails Detected' : '0 Selected' }}</span>
            </div>
            <div class="card-body">
                <!-- Segmented Control Tabs -->
                <div class="mb-3">
                    <!-- <ul class="nav segmented-control" id="recipients-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $isPasteMode ? '' : 'active' }}" id="crm-tab"
                                data-bs-toggle="pill" data-bs-target="#crm-pane" type="button" role="tab"
                                onclick="switchRecipientMethod('crm')">CRM Directory</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $isPasteMode ? 'active' : '' }}" id="paste-tab"
                                data-bs-toggle="pill" data-bs-target="#paste-pane" type="button" role="tab"
                                onclick="switchRecipientMethod('paste')">Paste Raw List</button>
                        </li>
                    </ul> -->
                </div>

                <div class="tab-content" id="recipients-tab-content">
                    <!-- Tab 1: CRM Checklist -->
                    <div class="tab-pane fade {{ $isPasteMode ? '' : 'show active' }}" id="crm-pane" role="tabpanel">

                        <!-- Filters Box -->
                        <div class="p-3 bg-zinc-50 rounded-3 mb-3"
                            style="background-color: #fafafa; border: 1px solid var(--border-color); border-radius: var(--radius-card);">
                            <div class="row g-3">
                                <!-- Type Radio Pill Filter -->
                                <div class="col-12 d-flex align-items-center gap-2 flex-wrap pb-2 border-bottom">
                                    <span class="form-label mb-0 text-zinc-500">Object Type:</span>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="crm-type-filter" id="crm-filter-all"
                                            value="all" checked onchange="filterRecipientsType('all')">
                                        <label class="btn btn-outline-secondary btn-xs fw-medium"
                                            for="crm-filter-all">All CRM Records</label>

                                        <input type="radio" class="btn-check" name="crm-type-filter"
                                            id="crm-filter-lead" value="Lead" onchange="filterRecipientsType('Lead')">
                                        <label class="btn btn-outline-secondary btn-xs fw-medium"
                                            for="crm-filter-lead"><i class="bi bi-person me-1"></i>Leads</label>

                                        <input type="radio" class="btn-check" name="crm-type-filter"
                                            id="crm-filter-contact" value="Contact"
                                            onchange="filterRecipientsType('Contact')">
                                        <label class="btn btn-outline-secondary btn-xs fw-medium"
                                            for="crm-filter-contact"><i
                                                class="bi bi-person-lines-fill me-1"></i>Contacts</label>

                                        <!-- <input type="radio" class="btn-check" name="crm-type-filter"
                                            id="crm-filter-account" value="Account"
                                            onchange="filterRecipientsType('Account')">
                                        <label class="btn btn-outline-secondary btn-xs fw-medium"
                                            for="crm-filter-account"><i class="bi bi-building me-1"></i>Accounts</label> -->
                                    </div>
                                </div>

                                <!-- 3 Multiselect Picklists (Dynamic from Salesforce) -->
                                <div class="col-12">
                                    <div class="row g-2">
                                        <!-- Category Multiselect -->
                                        @php
                                        $dealCategories = [
                                            'AC Adaptors',
                                            'AIO',
                                            'Audio Accessories',
                                            'Bar Code Scanner',
                                            'Barebone/Scrap Desktops',
                                            'Barebone/Scrap Laptops',
                                            'Cable Assemblies',
                                            'Camera',
                                            'CCTV/DVR',
                                            'Chromebook',
                                            'CPU',
                                            'CPU Fan',
                                            'Desktop C2D',
                                            'Desktop I Series',
                                            'Docking Stations',
                                            'Energy Audit Equipment',
                                            'E-Scrap',
                                            'Fax machine',
                                            'Gaming PC/Consoles',
                                            'HDD',
                                            'HighEnd Desktops',
                                            'HighEnd Laptops',
                                            'iMac',
                                            'iPads',
                                            'iPhones',
                                            'IP Phone',
                                            'Keyboard',
                                            'Laptop C2D',
                                            'Laptop I Series',
                                            'LCD',
                                            'MacBooks',
                                            'MacMini',
                                            'Memory',
                                            'Mobiles',
                                            'Mouse',
                                            'Networking Equipment',
                                            'Phone',
                                            'POS',
                                            'Power Cable',
                                            'Printers',
                                            'RAM',
                                            'Router',
                                            'Servers / Rack Servers',
                                            'Solar Panel',
                                            'Speakers',
                                            'Stylus',
                                            'Switch Board',
                                            'Tablet',
                                            'Thin Clients',
                                            'Toner/Cartridges',
                                            'Video Cards',
                                            'Wearables',
                                            'Workstation',
                                            'Other',
                                        ];
                                        @endphp
                                        <div class="col-md-4">
                                            <label class="form-label mb-1">Deal Category</label>
                                            <div class="dropdown w-100">
                                                <button
                                                    class="form-select text-start d-flex justify-content-between align-items-center w-100 py-1.5 px-3"
                                                    type="button" id="btn-filter-category" data-bs-toggle="dropdown"
                                                    aria-expanded="false"
                                                    style="font-size: 0.8125rem; font-weight: 500;">
                                                    <span class="text-truncate" id="label-filter-category">All
                                                        Categories</span>
                                                </button>
                                                <div class="dropdown-menu p-2 shadow-sm border w-100"
                                                    id="menu-filter-category" aria-labelledby="btn-filter-category"
                                                    style="max-height: 250px; overflow-y: auto;">
                                                    @foreach($dealCategories as $idx => $cat)
                                                        <div class="form-check py-1 px-3 d-flex align-items-center gap-2 rounded-2">
                                                            <input class="form-check-input filter-category-checkbox mt-0" type="checkbox" value="{{ $cat }}" id="cat-{{ $idx }}" onchange="filterRecipients()">
                                                            <label class="form-check-label text-zinc-800" for="cat-{{ $idx }}" style="font-size: 0.8125rem; font-weight: 500; cursor: pointer;">{{ $cat }}</label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Region Multiselect -->
                                        <div class="col-md-4">
                                            <label class="form-label mb-1">Region</label>
                                            <div class="dropdown w-100">
                                                <button
                                                    class="form-select text-start d-flex justify-content-between align-items-center w-100 py-1.5 px-3"
                                                    type="button" id="btn-filter-region" data-bs-toggle="dropdown"
                                                    aria-expanded="false"
                                                    style="font-size: 0.8125rem; font-weight: 500;">
                                                    <span class="text-truncate" id="label-filter-region">All
                                                        Regions</span>
                                                </button>
                                                <div class="dropdown-menu p-2 shadow-sm border w-100"
                                                    id="menu-filter-region" aria-labelledby="btn-filter-region"
                                                    style="max-height: 220px; overflow-y: auto;">
                                                    <div class="text-zinc-400 p-2 text-center"
                                                        style="font-size: 0.8125rem;">Loading regions...</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Country Multiselect -->
                                        <div class="col-md-4">
                                            <label class="form-label mb-1">Country</label>
                                            <div class="dropdown w-100">
                                                <button
                                                    class="form-select text-start d-flex justify-content-between align-items-center w-100 py-1.5 px-3"
                                                    type="button" id="btn-filter-country" data-bs-toggle="dropdown"
                                                    aria-expanded="false"
                                                    style="font-size: 0.8125rem; font-weight: 500;">
                                                    <span class="text-truncate" id="label-filter-country">All
                                                        Countries</span>
                                                </button>
                                                <div class="dropdown-menu p-2 shadow-sm border w-100"
                                                    id="menu-filter-country" aria-labelledby="btn-filter-country"
                                                    style="max-height: 220px; overflow-y: auto;">
                                                    <div class="text-zinc-400 p-2 text-center"
                                                        style="font-size: 0.8125rem;">Loading countries...</div>
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
                                    <span class="input-group-text bg-white border-end-0 text-zinc-400"><i
                                            class="bi bi-search"></i></span>
                                    <input type="text" id="rec-search" class="form-control border-start-0 ps-0"
                                        placeholder="Search contacts by name or ID..." onkeyup="filterRecipients()">
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-5 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-xs"
                                    onclick="toggleSelectAll(true)"><i class="bi bi-check-all"></i> Select All</button>
                                <button type="button" class="btn btn-outline-secondary btn-xs"
                                    onclick="toggleSelectAll(false)"><i class="bi bi-x"></i> Clear</button>
                            </div>
                        </div>

                        <!-- Recipient Checklist -->
                        <div class="recipient-list-box mb-1">
                            <ul class="list-group list-group-flush" id="recipient-list">
                                <li class="list-group-item text-center text-muted py-4">Loading Salesforce records...
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Tab 2: Paste Email List & CSV Upload -->
                    <div class="tab-pane fade {{ $isPasteMode ? 'show active' : '' }}" id="paste-pane" role="tabpanel">
                        <!-- CSV File Upload Dropzone Card -->
                        <div class="p-3 mb-3 bg-zinc-50 rounded-3" style="background-color: #fafafa; border: 1px solid var(--border-color); border-radius: var(--radius-card);">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-semibold text-zinc-900" style="font-size: 0.82rem;">
                                        <i class="bi bi-file-earmark-spreadsheet-fill text-indigo-600 me-1"></i> Upload Recipient CSV / Text File
                                    </span>
                                    <span class="badge bg-white text-zinc-600 border" style="font-size: 0.7rem;">.csv, .txt, .tsv</span>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <a href="{{ route('campaign.sample_csv') }}" class="btn btn-outline-secondary btn-xs" title="Download formatted sample CSV template">
                                        <i class="bi bi-download me-1"></i> Sample CSV
                                    </a>
                                    <button type="button" class="btn btn-primary btn-xs" onclick="document.getElementById('csv-file-input').click()">
                                        <i class="bi bi-folder2-open me-1"></i> Browse File
                                    </button>
                                    <input type="file" id="csv-file-input" accept=".csv,.txt,.tsv" class="d-none" onchange="handleCsvFileInput(this.files)">
                                </div>
                            </div>

                            <!-- Interactive Drag-and-Drop Area -->
                            <div id="csv-dropzone" class="p-3 text-center rounded-3 position-relative" 
                                style="border: 2px dashed #cbd5e1; background-color: #ffffff; cursor: pointer; transition: all 0.2s ease;"
                                onclick="document.getElementById('csv-file-input').click()">
                                <i class="bi bi-cloud-arrow-up text-indigo-600 fs-3 d-block mb-1"></i>
                                <div class="fw-semibold text-zinc-800" style="font-size: 0.84rem;">
                                    Drag and drop your CSV file here, or <span class="text-indigo-600 text-decoration-underline">browse to choose</span>
                                </div>
                                <small class="text-zinc-500 d-block mt-0.5" style="font-size: 0.74rem;">
                                    Auto-detects email columns (<code class="text-indigo-700">email</code>, <code class="text-indigo-700">recipient</code>, etc.) or scans multi-column sheets. Up to 10MB.
                                </small>
                            </div>

                            <!-- Upload Status & Summary Box (Hidden until file parsed) -->
                            <div id="csv-status-card" class="d-none mt-2 p-2.5 rounded-2 bg-white border border-indigo-200">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-success-soft text-success p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                            <i class="bi bi-check2 text-success fw-bold"></i>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-zinc-900" style="font-size: 0.8rem;">
                                                <span id="csv-status-filename">filename.csv</span>
                                                <span class="badge bg-success-soft text-emerald-800 ms-1" id="csv-status-count-badge">0 Emails Extracted</span>
                                            </div>
                                            <small class="text-zinc-500" style="font-size: 0.72rem;" id="csv-status-details">
                                                Parsed 0 rows. Duplicates removed.
                                            </small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-secondary btn-xs active" id="btn-import-replace" onclick="setImportMode('replace')">Replace List</button>
                                            <button type="button" class="btn btn-outline-secondary btn-xs" id="btn-import-append" onclick="setImportMode('append')">Append</button>
                                        </div>
                                        <button type="button" class="btn btn-outline-danger btn-xs" onclick="clearCsvImport()" title="Remove imported file">
                                            <i class="bi bi-x-circle me-1"></i> Clear File
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Divider / Raw Paste Area Header -->
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <div>
                                <span class="fw-semibold text-zinc-800" style="font-size: 0.8rem;">
                                    <i class="bi bi-pencil-square text-zinc-500 me-1"></i> Recipient Email List (Manual or CSV Extracted)
                                </span>
                                <small class="text-zinc-500 d-block" style="font-size: 0.74rem;">
                                    One email per line or separated by commas. Opt-out and compliance records will be verified automatically.
                                </small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="cleanAndDeduplicatePasted()" title="Remove duplicate emails and normalize format">
                                    <i class="bi bi-magic me-1"></i> Clean &amp; Dedupe
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="clearPastedList()" title="Clear all email text">
                                    <i class="bi bi-trash me-1"></i> Clear
                                </button>
                                <span class="badge bg-primary-soft text-indigo-700 fw-semibold px-2 py-1" id="pasted-email-counter">0 detected</span>
                            </div>
                        </div>

                        <div>
                            <textarea class="form-control font-monospace" id="pasted-emails" rows="6"
                                placeholder="john.doe@company.com&#10;jane.smith@partner.net&#10;david@leads.com"
                                style="font-size: 0.82rem; line-height: 1.5;" oninput="updatePastedCount()"></textarea>
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
                <h6 class="mb-0 fw-semibold text-zinc-900"><i class="bi bi-envelope-open me-1.5 text-zinc-500"></i>
                    Message Content & Configuration</h6>
            </div>
            <div class="card-body">
                <form id="campaign-form" onsubmit="event.preventDefault();">
                    <!-- Templates Toolbar -->
                    <div class="row g-2 mb-3 align-items-end">
                        <div class="col-md-7">
                            <label for="template-select" class="form-label mb-1 fw-semibold text-zinc-700"
                                style="font-size: 0.8rem;">
                                <i class="bi bi-journal-bookmark-fill me-1 text-primary"></i> Email Template
                            </label>
                            <div class="input-group">
                                <select id="template-select" class="form-select" onchange="onTemplateSelectionChange()">
                                    <option value="">-- No Template Selected --</option>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" id="btn-apply-template"
                                    onclick="loadSelectedTemplate()" title="Apply Template into Editor" disabled>
                                    <i class="bi bi-box-arrow-in-down me-1"></i> Apply
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btn-preview-template"
                                    onclick="previewSelectedTemplate()" title="Preview Template" disabled>
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btn-edit-template"
                                    onclick="openEditTemplateModal()" title="Edit Template" disabled>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" id="btn-delete-template"
                                    onclick="deleteSelectedTemplate()" title="Delete Template" disabled>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-5 d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary w-50"
                                onclick="openCreateTemplateModal()">
                                <i class="bi bi-plus-lg me-1"></i> New Template
                            </button>
                            <button type="button" class="btn btn-outline-secondary w-50" onclick="saveAsTemplate()"
                                title="Save Current Subject & Body as New Template">
                                <i class="bi bi-bookmark-plus me-1"></i> Save Current
                            </button>
                        </div>
                    </div>

                    <!-- Subject & Attachments Row -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="subject" class="form-label mb-1 fw-semibold text-zinc-700"
                                style="font-size: 0.8rem;">
                                Subject Line <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="subject"
                                placeholder="e.g. Quick question regarding @{{CompanyName}}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1 fw-semibold text-zinc-700" style="font-size: 0.8rem;">
                                <i class="bi bi-paperclip me-1"></i> Attachments
                            </label>
                            <input type="file" id="attachments-input" class="form-control" multiple
                                onchange="handleFileSelect()">
                            <div id="attachments-list" class="mt-2 d-flex flex-wrap gap-1.5"></div>
                        </div>
                    </div>

                    <!-- Reply-To Settings Row (Visible to all roles) -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="reply-to" class="form-label mb-1 fw-semibold text-zinc-700" style="font-size: 0.8rem;">
                                <i class="bi bi-reply-fill me-1 text-primary"></i> Reply-To Email <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="form-control" id="reply-to" name="reply_to"
                                value="{{ config('pabbly.reply_to', 'support@b2bexportsllc.com') }}"
                                placeholder="support@b2bexportsllc.com" required>
                            <div class="form-text text-zinc-500" style="font-size: 0.72rem;">
                                Customer responses will be directed to this inbox (Default: support@b2bexportsllc.com).
                            </div>
                        </div>
                    </div>

                    <!-- Sender Info Row (Visible only to Admin) -->
                    <div
                        class="row g-3 mb-3 {{ (session('role', Auth::user()->role ?? '') !== 'admin') ? 'd-none' : '' }}">
                        <div class="col-md-6">
                            <label for="sending-domain" class="form-label mb-1">Outbound Domain</label>
                            <select class="form-select" id="sending-domain" onchange="updateFromEmail()" required>
                                <!-- Populated dynamically -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="from-address" class="form-label mb-1">From Address</label>
                            <input type="email" class="form-control" id="from-address" placeholder="rma@proitbuyer.com"
                                value="rma@proitbuyer.com" required>
                        </div>
                    </div>

                    <!-- Dynamic Merge Variable Insertion Pills -->
                    <div class="mb-2 p-2.5 rounded border" style="background-color: #f8fafc;">
                        <div class="d-flex justify-content-between align-items-center mb-1.5 flex-wrap gap-1">
                            <span class="fw-semibold text-zinc-700" style="font-size: 0.775rem;">
                                <i class="bi bi-magic text-primary me-1"></i> Merge Fields (Click to insert at editor
                                cursor):
                            </span>
                            <span class="text-zinc-500" style="font-size: 0.725rem;">
                                <i class="bi bi-check-circle-fill text-success me-1"></i> Dynamically mapped
                                per-recipient
                            </span>
                        </div>
                        <div class="d-flex flex-wrap gap-1.5 align-items-center">
                            <button type="button" class="btn btn-sm btn-white border py-0.5 px-2 text-primary fw-medium"
                                onclick="insertMergeField('@{{FirstName}}')"
                                title="Recipient First Name (Lead/Contact CRM record)">
                                <code>@{{FirstName}}</code>
                            </button>
                            <button type="button"
                                class="btn btn-sm btn-white border py-0.5 px-2 text-zinc-700 fw-medium"
                                onclick="insertMergeField('@{{LastName}}')" title="Recipient Last Name">
                                <code>@{{LastName}}</code>
                            </button>
                            <button type="button"
                                class="btn btn-sm btn-white border py-0.5 px-2 text-zinc-700 fw-medium"
                                onclick="insertMergeField('@{{FullName}}')" title="Recipient Full Name">
                                <code>@{{FullName}}</code>
                            </button>
                            <button type="button"
                                class="btn btn-sm btn-white border py-0.5 px-2 text-zinc-700 fw-medium"
                                onclick="insertMergeField('@{{CompanyName}}')"
                                title="Recipient Company or Account Name">
                                <code>@{{CompanyName}}</code>
                            </button>
                            <button type="button"
                                class="btn btn-sm btn-white border py-0.5 px-2 text-zinc-700 fw-medium"
                                onclick="insertMergeField('@{{Email}}')" title="Recipient Email Address">
                                <code>@{{Email}}</code>
                            </button>
                            <button type="button"
                                class="btn btn-sm btn-white border py-0.5 px-2 text-zinc-700 fw-medium"
                                onclick="insertMergeField('@{{Phone}}')" title="Recipient Phone Number">
                                <code>@{{Phone}}</code>
                            </button>
                            <button type="button"
                                class="btn btn-sm btn-white border py-0.5 px-2 text-zinc-700 fw-medium"
                                onclick="insertMergeField('@{{OwnerName}}')" title="Assigned Salesforce Owner">
                                <code>@{{OwnerName}}</code>
                            </button>
                            <button type="button"
                                class="btn btn-sm btn-white border py-0.5 px-2 text-zinc-700 fw-medium"
                                onclick="insertMergeField('@{{SenderName}}')" title="Your Sender Name">
                                <code>@{{SenderName}}</code>
                            </button>
                            <button type="button"
                                class="btn btn-sm btn-white border py-0.5 px-2 text-zinc-700 fw-medium"
                                onclick="insertMergeField('@{{CompanyWebsite}}')"
                                title="Recipient or Company Website URL">
                                <code>@{{CompanyWebsite}}</code>
                            </button>
                            <button type="button"
                                class="btn btn-sm btn-white border py-0.5 px-2 text-zinc-700 fw-medium"
                                onclick="insertMergeField('@{{CompanyEmail}}')" title="Company Contact Email">
                                <code>@{{CompanyEmail}}</code>
                            </button>
                            <button type="button"
                                class="btn btn-sm btn-white border py-0.5 px-2 text-zinc-700 fw-medium"
                                onclick="insertMergeField('@{{CompanyPhone}}')" title="Company Contact Phone">
                                <code>@{{CompanyPhone}}</code>
                            </button>
                            <button type="button" class="btn btn-sm btn-white border py-0.5 px-2 text-success fw-medium"
                                onclick="insertMergeField('@{{Signature}}')" title="Your Rendered Email Signature">
                                <code>@{{Signature}}</code>
                            </button>
                        </div>
                    </div>

                    <!-- Dual-Mode Email Body Editor (Visual Rich Text + Lossless HTML Template Engine) -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1.5 flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <label class="form-label mb-0 fw-semibold text-zinc-700" style="font-size: 0.8rem;">
                                    Email Body Content <span class="text-danger">*</span>
                                </label>
                                <span id="editor-active-mode-badge" class="badge bg-primary-soft text-primary"
                                    style="font-size: 0.7rem;">
                                    <i class="bi bi-fonts me-1"></i> Visual Mode
                                </span>
                            </div>
                            <div class="btn-group btn-group-sm" role="group" id="editor-mode-toggle">
                                <button type="button" class="btn btn-sm btn-outline-secondary active"
                                    id="btn-mode-visual" onclick="setEditorMode('visual')"
                                    title="Visual Rich Text Editor for standard formatting">
                                    <i class="bi bi-fonts me-1"></i> Visual Rich Text
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-mode-html"
                                    onclick="setEditorMode('html')"
                                    title="Raw HTML Source Editor (Lossless tables & responsive email templates)">
                                    <i class="bi bi-code-slash me-1"></i> HTML Source / Template
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-mode-preview"
                                    onclick="setEditorMode('preview')"
                                    title="Live Pixel-Perfect Rendered Email Preview">
                                    <i class="bi bi-eye me-1"></i> Live Rendered View
                                </button>
                            </div>
                        </div>

                        <div id="editor-wrapper" class="border rounded"
                            style="overflow: hidden; border-color: var(--border-color) !important;">
                            <!-- Visual Quill Editor -->
                            <div id="quill-wrapper">
                                <div id="editor-container"></div>
                            </div>

                            <!-- Raw HTML Code Editor (Preserves Tables & CSS losslessly) -->
                            <div id="html-wrapper" class="d-none">
                                <div class="p-2 px-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2"
                                    style="font-size: 0.75rem;">
                                    <div class="text-zinc-600">
                                        <i class="bi bi-shield-check text-success me-1"></i> <strong>Lossless HTML
                                            Mode:</strong> Full email tables, inline styles, hero images & structures
                                        are preserved 100% untouched.
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-xs btn-outline-primary"
                                            onclick="setEditorMode('preview')">
                                            <i class="bi bi-eye me-1"></i> View Rendered Preview
                                        </button>
                                    </div>
                                </div>
                                <textarea id="html-source-editor" class="form-control font-monospace border-0 p-3"
                                    rows="16"
                                    style="font-size: 0.8rem; line-height: 1.5; background: #fafafa; border-radius: 0; outline: none; box-shadow: none;"
                                    placeholder="Paste or edit raw HTML email template code here..."></textarea>
                            </div>

                            <!-- Live Rendered View -->
                            <div id="preview-wrapper" class="d-none">
                                <div class="p-2 px-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2"
                                    style="font-size: 0.75rem;">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-zinc-600 fw-semibold">View as:</span>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-xs btn-outline-secondary active"
                                                id="btn-vp-desktop" onclick="setPreviewViewport('desktop')">
                                                <i class="bi bi-laptop me-1"></i> Desktop (600px)
                                            </button>
                                            <button type="button" class="btn btn-xs btn-outline-secondary"
                                                id="btn-vp-mobile" onclick="setPreviewViewport('mobile')">
                                                <i class="bi bi-phone me-1"></i> Mobile (380px)
                                            </button>
                                            <button type="button" class="btn btn-xs btn-outline-secondary"
                                                id="btn-vp-full" onclick="setPreviewViewport('full')">
                                                <i class="bi bi-arrows-fullscreen me-1"></i> Full Width
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-xs btn-outline-primary"
                                            onclick="setEditorMode('html')">
                                            <i class="bi bi-pencil me-1"></i> Edit HTML Source
                                        </button>
                                    </div>
                                </div>
                                <div class="p-3"
                                    style="background-color: #f4f7f9; min-height: 380px; max-height: 600px; overflow-y: auto;">
                                    <div id="editor-live-preview-box"
                                        style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); overflow: hidden;">
                                        <!-- Rendered HTML will appear here -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-text text-zinc-500 d-flex justify-content-between align-items-center mt-1"
                            style="font-size: 0.74rem;">
                            <span><code>@{{FirstName}}</code> maps to the recipient's first name. For full marketing
                                templates with tables, HTML Source mode delivers pixel-perfect rendering in Gmail &
                                Outlook.</span>
                            <span id="editor-type-indicator">Quill Rich Text / HTML Engine</span>
                        </div>
                    </div>

                    <!-- Signatures & Schedule Grid -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="signature-select" class="form-label mb-0 fw-semibold text-zinc-700"
                                    style="font-size: 0.8rem;">
                                    <i class="bi bi-pen me-1 text-primary"></i> Email Signature
                                </label>
                                <a href="{{ route('signatures.index') }}" target="_blank"
                                    class="text-decoration-none text-primary" style="font-size: 0.75rem;">
                                    <i class="bi bi-gear me-1"></i> Manage Signatures
                                </a>
                            </div>
                            <div class="input-group">
                                <select id="signature-select" class="form-select"
                                    onchange="onSignatureSelectionChange()">
                                    <option value="">-- No Signature Selected --</option>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" id="btn-insert-sig"
                                    onclick="insertSelectedSignature()" title="Insert Signature into Body Editor"
                                    disabled>
                                    <i class="bi bi-box-arrow-in-down me-1"></i> Insert
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btn-preview-sig"
                                    onclick="previewSelectedSignature()" title="Preview Signature" disabled>
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text text-zinc-500" style="font-size: 0.72rem;">
                                The selected signature is auto-attached to outbound emails or substituted where
                                <code>@{{Signature}}</code> appears.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="schedule-datetime" class="form-label mb-1 fw-semibold text-zinc-700"
                                style="font-size: 0.8rem;">
                                <i class="bi bi-clock me-1 text-primary"></i> Schedule Send (Atlanta EST/EDT)
                            </label>
                            <div class="input-group">
                                <input type="datetime-local" id="schedule-datetime" class="form-control">
                                <button class="btn btn-outline-secondary" type="button"
                                    onclick="showTimezoneChecker()"><i class="bi bi-globe"></i> Timezones</button>
                            </div>
                            <div class="form-text text-zinc-500" style="font-size: 0.72rem;">Leave empty to dispatch as
                                soon as campaign is approved.</div>
                        </div>
                    </div>

                    <!-- Action bar -->
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <button type="button" class="btn btn-outline-primary" onclick="openLivePreviewModal()">
                            <i class="bi bi-eye-fill me-1"></i> Preview Email
                        </button>
                        @can('bulk-mail.send')
                        <button type="button" class="btn btn-primary" onclick="runPreSendValidation()">
                            <i class="bi bi-shield-check me-1"></i> Verify & Dispatch
                        </button>
                        @else
                        <button type="button" class="btn btn-secondary" disabled
                            title="You do not have permission to dispatch bulk campaigns.">
                            <i class="bi bi-shield-lock me-1"></i> Dispatch Restricted
                        </button>
                        @endcan
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Pre-Send Validation Summary Panel -->
    <div class="col-12 d-none" id="validation-summary-card">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-zinc-900"><i class="bi bi-shield-lock me-1.5"></i> Pre-Send Compliance
                    Summary</span>
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
                            <button
                                class="accordion-button collapsed fw-medium text-rose-700 bg-rose-soft py-2 px-3 rounded-2"
                                type="button" data-bs-toggle="collapse" data-bs-target="#collapseBlockedList"
                                style="font-size: 0.8rem;">
                                View Blocked Records Breakdown
                            </button>
                        </h2>
                        <div id="collapseBlockedList" class="accordion-collapse collapse"
                            data-bs-parent="#blockedDetailsAccordion">
                            <div class="accordion-body px-0 py-2">
                                <ul class="list-group list-group-flush" id="validation-error-list"
                                    style="max-height: 250px; overflow-y: auto;">
                                    <!-- Populated dynamically -->
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Final send actions -->
                <div class="d-flex justify-content-end gap-2 align-items-center pt-2.5 border-top">
                    <button class="btn btn-outline-secondary btn-sm" onclick="hideValidationSummary()">Cancel</button>
                    @can('bulk-mail.send')
                    <button class="btn btn-primary btn-sm" id="btn-submit-send" onclick="submitCampaign()">
                        <i class="bi bi-send-fill"></i> Confirm Dispatch
                    </button>
                    @else
                    <button class="btn btn-secondary btn-sm" disabled>
                        <i class="bi bi-shield-lock"></i> Dispatch Restricted
                    </button>
                    @endcan
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
                <h6 class="modal-title fw-semibold text-zinc-900"><i class="bi bi-globe me-1.5"></i> Timezone Conversion
                    Matrix</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                    style="font-size: 0.75rem;"></button>
            </div>
            <div class="modal-body py-3 px-4">
                <p class="text-zinc-500 mb-3" style="font-size: 0.78rem;">Converted from scheduled Atlanta time: <strong
                        class="text-zinc-900" id="tz-atlanta-input-label">N/A</strong></p>
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

<!-- Modal: Create / Edit Template -->
<div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--radius-card);">
            <div class="modal-header border-bottom pb-3">
                <h6 class="modal-title fw-bold text-zinc-900" id="templateModalLabel">
                    <i class="bi bi-journal-bookmark-fill text-primary me-2"></i> Create Email Template
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="tmpl_modal_id" value="">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-zinc-700" style="font-size: 0.8rem;">
                        Template Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="tmpl_modal_name"
                        placeholder="e.g. Sales Intro - Healthcare" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-zinc-700" style="font-size: 0.8rem;">
                        Default Subject Line <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="tmpl_modal_subject"
                        placeholder="e.g. Partnership Opportunity for @{{CompanyName}}" required>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label fw-semibold text-zinc-700 mb-0" style="font-size: 0.8rem;">
                            Template Body (HTML / Text) <span class="text-danger">*</span>
                        </label>
                        <button type="button" class="btn btn-outline-secondary btn-xs"
                            onclick="copyEditorContentToTemplateModal()">
                            <i class="bi bi-clipboard me-1"></i> Copy from Main Editor
                        </button>
                    </div>
                    <textarea class="form-control font-monospace" id="tmpl_modal_body" rows="8"
                        placeholder="<p>Hi @{{FirstName}},</p><p>I noticed your work at @{{CompanyName}}...</p>"
                        required style="font-size: 0.825rem;"></textarea>
                    <div class="form-text text-zinc-500" style="font-size: 0.72rem;">
                        Supports merge tags: <code>@{{FirstName}}</code>, <code>@{{LastName}}</code>,
                        <code>@{{FullName}}</code>, <code>@{{CompanyName}}</code>, <code>@{{Email}}</code>,
                        <code>@{{Phone}}</code>, <code>@{{Signature}}</code>.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top pt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm px-4" id="btn-save-template-modal"
                    onclick="saveTemplateFromModal()">
                    <i class="bi bi-check-lg me-1"></i> Save Template
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Preview Template -->
<div class="modal fade" id="templatePreviewModal" tabindex="-1" aria-labelledby="templatePreviewModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--radius-card);">
            <div class="modal-header border-bottom pb-3">
                <h6 class="modal-title fw-bold text-zinc-900" id="templatePreviewModalLabel">
                    <i class="bi bi-eye-fill text-primary me-2"></i> Template Preview: <span id="prev_tmpl_name"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <span class="text-zinc-500 fw-semibold"
                        style="font-size: 0.75rem; text-transform: uppercase;">Subject Line:</span>
                    <div class="p-2 bg-light rounded border fw-medium text-zinc-900 mt-1" id="prev_tmpl_subject"></div>
                </div>
                <div>
                    <span class="text-zinc-500 fw-semibold" style="font-size: 0.75rem; text-transform: uppercase;">Body
                        Content:</span>
                    <div class="p-3 bg-white rounded border mt-1" id="prev_tmpl_body"
                        style="min-height: 200px; max-height: 400px; overflow-y: auto;"></div>
                </div>
            </div>
            <div class="modal-footer border-top pt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm px-4" onclick="applyPreviewedTemplate()">
                    <i class="bi bi-box-arrow-in-down me-1"></i> Apply into Campaign Editor
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Preview Signature -->
<div class="modal fade" id="signaturePreviewModal" tabindex="-1" aria-labelledby="signaturePreviewModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--radius-card);">
            <div class="modal-header border-bottom pb-3">
                <h6 class="modal-title fw-bold text-zinc-900">
                    <i class="bi bi-pen-fill text-primary me-2"></i> Signature Preview: <span id="prev_sig_name"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="p-3 bg-white rounded border" id="prev_sig_body" style="min-height: 120px;"></div>
            </div>
            <div class="modal-footer border-top pt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm"
                    onclick="insertSelectedSignature(); bootstrap.Modal.getInstance(document.getElementById('signaturePreviewModal')).hide();">
                    <i class="bi bi-box-arrow-in-down me-1"></i> Insert into Editor
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Live Email Preview (Personalized per Recipient) -->
<div class="modal fade" id="campaignPreviewModal" tabindex="-1" aria-labelledby="campaignPreviewModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--radius-card);">
            <div class="modal-header border-bottom pb-3">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="modal-title fw-bold text-zinc-900 mb-0">
                        <i class="bi bi-envelope-paper-fill text-primary me-2"></i> Live Personalized Email Preview
                    </h6>
                    <span class="badge bg-success-soft" style="font-size: 0.72rem;">Dynamic CRM Resolution</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-7">
                        <label class="form-label fw-semibold text-zinc-700" style="font-size: 0.8rem;">
                            <i class="bi bi-person-check me-1 text-primary"></i> Preview as Recipient:
                        </label>
                        <select id="prev_recipient_select" class="form-select form-select-sm"
                            onchange="refreshLivePreviewModal()">
                            <!-- Populated with selected recipients or sample -->
                        </select>
                    </div>
                    <div class="col-12 col-md-5">
                        <div class="p-2 rounded bg-light border text-zinc-600" style="font-size: 0.75rem;">
                            <div><strong>Recipient Email:</strong> <span id="prev_rec_email"
                                    class="font-monospace text-primary"></span></div>
                            <div><strong>Company / Account:</strong> <span id="prev_rec_company"></span></div>
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3 mb-3 bg-light">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto text-zinc-500 fw-semibold" style="font-size: 0.78rem; width: 70px;">FROM:
                        </div>
                        <div class="col text-zinc-900 fw-medium" style="font-size: 0.825rem;" id="prev_from_field">
                            rma@proitbuyer.com</div>
                    </div>
                    <div class="row g-2 align-items-center mt-1">
                        <div class="col-auto text-zinc-500 fw-semibold" style="font-size: 0.78rem; width: 70px;">REPLY-TO:
                        </div>
                        <div class="col text-zinc-900 fw-medium font-monospace" style="font-size: 0.825rem;" id="prev_reply_to_field">
                            support@b2bexportsllc.com</div>
                    </div>
                    <div class="row g-2 align-items-center mt-1">
                        <div class="col-auto text-zinc-500 fw-semibold" style="font-size: 0.78rem; width: 70px;">TO:
                        </div>
                        <div class="col text-zinc-900 fw-medium font-monospace" style="font-size: 0.825rem;"
                            id="prev_to_field">john.doe@example.com</div>
                    </div>
                    <div class="row g-2 align-items-center mt-1 pt-1 border-top">
                        <div class="col-auto text-zinc-500 fw-semibold" style="font-size: 0.78rem; width: 70px;">
                            SUBJECT:</div>
                        <div class="col text-zinc-900 fw-bold" style="font-size: 0.875rem;" id="prev_subject_field">
                        </div>
                    </div>
                </div>

                <div class="border rounded p-4 bg-white shadow-2xs"
                    style="min-height: 250px; max-height: 450px; overflow-y: auto;">
                    <div id="prev_body_field" class="email-preview-rendered-body"></div>
                </div>
            </div>
            <div class="modal-footer border-top pt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm px-4"
                    onclick="bootstrap.Modal.getInstance(document.getElementById('campaignPreviewModal')).hide(); runPreSendValidation();">
                    <i class="bi bi-shield-check me-1"></i> Proceed to Verify & Dispatch
                </button>
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
    let recipientMethod = '{{ $isPasteMode ? "paste" : "crm" }}'; // 'crm' or 'paste'

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

    // --- Dual-Mode Editor Controller (Visual Quill vs Lossless HTML Source vs Live Preview) ---
    let currentEditorMode = 'visual'; // 'visual', 'html', 'preview'

    function getCampaignBodyContent() {
        if (currentEditorMode === 'html' || currentEditorMode === 'preview') {
            const ta = document.getElementById('html-source-editor');
            return ta ? ta.value : '';
        }
        // If in visual mode, check if html-source-editor contains an uncorrupted HTML table template
        const htmlVal = document.getElementById('html-source-editor') ? document.getElementById('html-source-editor').value : '';
        if (htmlVal && /<table|<tbody|<tr|<td|<style|<div style=/i.test(htmlVal)) {
            return htmlVal;
        }
        return (quill && quill.root) ? quill.root.innerHTML : '';
    }

    function setEditorMode(mode) {
        if (mode === currentEditorMode) return;

        const quillWrap = document.getElementById('quill-wrapper');
        const htmlWrap = document.getElementById('html-wrapper');
        const prevWrap = document.getElementById('preview-wrapper');
        const htmlEditor = document.getElementById('html-source-editor');
        const badge = document.getElementById('editor-active-mode-badge');
        const indicator = document.getElementById('editor-type-indicator');

        const btnVisual = document.getElementById('btn-mode-visual');
        const btnHtml = document.getElementById('btn-mode-html');
        const btnPreview = document.getElementById('btn-mode-preview');

        if (mode === 'visual') {
            const htmlCode = htmlEditor ? htmlEditor.value.trim() : '';
            if (htmlCode && /<table|<tbody|<tr|<td/i.test(htmlCode)) {
                const proceed = confirm("Notice: This template contains custom responsive HTML tables and inline styling.\n\nOpening it in the Visual Editor will cause Quill to simplify nested tables. For marketing email templates, we recommend staying in HTML Source mode or checking in Live Rendered View.\n\nDo you want to switch to Visual Editor anyway?");
                if (!proceed) return;
            }
            if (htmlCode && quill) {
                quill.root.innerHTML = htmlCode;
            }
            quillWrap.classList.remove('d-none');
            htmlWrap.classList.add('d-none');
            prevWrap.classList.add('d-none');

            btnVisual.classList.add('active');
            btnHtml.classList.remove('active');
            btnPreview.classList.remove('active');

            badge.className = 'badge bg-primary-soft text-primary';
            badge.innerHTML = '<i class="bi bi-fonts me-1"></i> Visual Mode';
            indicator.textContent = 'Quill Rich Text';
            currentEditorMode = 'visual';
        } else if (mode === 'html') {
            if (currentEditorMode === 'visual') {
                if ((!htmlEditor.value || !htmlEditor.value.trim()) && quill && quill.root) {
                    htmlEditor.value = quill.root.innerHTML;
                }
            }
            quillWrap.classList.add('d-none');
            htmlWrap.classList.remove('d-none');
            prevWrap.classList.add('d-none');

            btnVisual.classList.remove('active');
            btnHtml.classList.add('active');
            btnPreview.classList.remove('active');

            badge.className = 'badge bg-success-soft text-success';
            badge.innerHTML = '<i class="bi bi-code-slash me-1"></i> Lossless HTML Mode';
            indicator.textContent = 'Raw HTML Source (Tables & Styles 100% Preserved)';
            currentEditorMode = 'html';
        } else if (mode === 'preview') {
            const content = (currentEditorMode === 'html') ? htmlEditor.value : (htmlEditor.value.trim() ? htmlEditor.value : (quill ? quill.root.innerHTML : ''));
            document.getElementById('editor-live-preview-box').innerHTML = content;

            quillWrap.classList.add('d-none');
            htmlWrap.classList.add('d-none');
            prevWrap.classList.remove('d-none');

            btnVisual.classList.remove('active');
            btnHtml.classList.remove('active');
            btnPreview.classList.add('active');

            badge.className = 'badge bg-info-soft text-info';
            badge.innerHTML = '<i class="bi bi-eye me-1"></i> Live Rendered View';
            indicator.textContent = 'Live Email Client Rendering';
            currentEditorMode = 'preview';
        }
    }

    function setPreviewViewport(type) {
        const box = document.getElementById('editor-live-preview-box');
        const btnDesk = document.getElementById('btn-vp-desktop');
        const btnMob = document.getElementById('btn-vp-mobile');
        const btnFull = document.getElementById('btn-vp-full');

        btnDesk.classList.remove('active');
        btnMob.classList.remove('active');
        btnFull.classList.remove('active');

        if (type === 'mobile') {
            box.style.maxWidth = '380px';
            btnMob.classList.add('active');
        } else if (type === 'full') {
            box.style.maxWidth = '100%';
            btnFull.classList.add('active');
        } else {
            box.style.maxWidth = '600px';
            btnDesk.classList.add('active');
        }
    }

    function formatHtmlSource() {
        const ta = document.getElementById('html-source-editor');
        if (!ta || !ta.value.trim()) return;
        let formatted = ta.value
            .replace(/>\s*</g, '>\n<')
            .replace(/\n\s*\n/g, '\n');
        ta.value = formatted;
    }

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
            updatePastedCount();
        } else {
            document.getElementById('selected-count').innerText = `${selectedRecords.size} Selected`;
        }
    }

    function updatePastedCount() {
        const textarea = document.getElementById('pasted-emails');
        const text = textarea ? textarea.value : '';
        const emails = text.split(/[\n,]+/).map(e => e.trim()).filter(e => e.length > 0 && e.includes('@'));
        const counter = document.getElementById('pasted-email-counter');
        if (counter) {
            counter.innerText = `${emails.length} detected`;
        }
        if (recipientMethod === 'paste') {
            document.getElementById('selected-count').innerText = `${emails.length} Emails Detected`;
        }
    }

    // --- CSV File Upload, Drag-and-Drop & Email Extraction ---
    let lastParsedCsvEmails = [];
    let importMode = 'replace'; // 'replace' or 'append'
    let currentCsvFileName = '';
    let currentCsvTotalRows = 0;

    function handleCsvFileInput(files) {
        if (!files || files.length === 0) return;
        parseCsvFileClient(files[0]);
    }

    function initCsvDropzone() {
        const dropzone = document.getElementById('csv-dropzone');
        if (!dropzone) return;

        ['dragenter', 'dragover'].forEach(name => {
            dropzone.addEventListener(name, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.style.borderColor = '#6366f1';
                dropzone.style.backgroundColor = '#eef2ff';
            });
        });

        ['dragleave', 'drop'].forEach(name => {
            dropzone.addEventListener(name, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.style.borderColor = '#cbd5e1';
                dropzone.style.backgroundColor = '#ffffff';
            });
        });

        dropzone.addEventListener('drop', (e) => {
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                parseCsvFileClient(e.dataTransfer.files[0]);
            }
        });
    }

    function parseCsvFileClient(file) {
        if (!file) return;

        const validExtensions = ['.csv', '.txt', '.tsv'];
        const fileName = file.name;
        const fileExt = fileName.substring(fileName.lastIndexOf('.')).toLowerCase();

        if (!validExtensions.includes(fileExt)) {
            alert("Please upload a valid .csv, .txt, or .tsv file.");
            return;
        }

        currentCsvFileName = fileName;

        const reader = new FileReader();
        reader.onload = function(e) {
            const content = e.target.result;
            processCsvContent(content, fileName);
        };
        reader.onerror = function() {
            uploadCsvToServer(file);
        };
        reader.readAsText(file);
    }

    function parseCsvLine(line) {
        const result = [];
        let insideQuote = false;
        let entry = '';
        for (let i = 0; i < line.length; i++) {
            const c = line[i];
            if (c === '"') {
                if (insideQuote && line[i + 1] === '"') {
                    entry += '"';
                    i++;
                } else {
                    insideQuote = !insideQuote;
                }
            } else if ((c === ',' || c === '\t' || c === ';') && !insideQuote) {
                result.push(entry.trim());
                entry = '';
            } else {
                entry += c;
            }
        }
        result.push(entry.trim());
        return result;
    }

    function processCsvContent(text, fileName) {
        if (!text || !text.trim()) {
            alert("The uploaded file appears to be empty.");
            return;
        }

        const lines = text.split(/\r?\n/).filter(line => line.trim().length > 0);
        if (lines.length === 0) {
            alert("No data lines found in the uploaded file.");
            return;
        }

        currentCsvTotalRows = lines.length;

        // Parse first line to check for headers
        const headerRow = parseCsvLine(lines[0]);
        let emailColIdx = -1;
        const headerKeywords = ['email', 'e-mail', 'mail', 'email address', 'email_address', 'recipient', 'recipient_email', 'contact email', 'contact_email', 'work email'];

        headerRow.forEach((col, idx) => {
            const clean = col.trim().toLowerCase().replace(/[\"\'\`]/g, '');
            if (headerKeywords.includes(clean)) {
                emailColIdx = idx;
            }
        });

        const extractedEmails = [];
        const seen = new Set();
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/i;

        const startIdx = (emailColIdx !== -1) ? 1 : 0;

        for (let i = startIdx; i < lines.length; i++) {
            const row = parseCsvLine(lines[i]);
            if (emailColIdx !== -1 && row[emailColIdx] !== undefined) {
                const cell = row[emailColIdx].replace(/[\"\'\`]/g, '').trim();
                if (emailRegex.test(cell)) {
                    const norm = cell.toLowerCase();
                    if (!seen.has(norm)) {
                        seen.add(norm);
                        extractedEmails.push(cell);
                    }
                }
            } else {
                row.forEach(cell => {
                    const cleaned = cell.replace(/[\"\'\`]/g, '').trim();
                    if (emailRegex.test(cleaned)) {
                        const norm = cleaned.toLowerCase();
                        if (!seen.has(norm)) {
                            seen.add(norm);
                            extractedEmails.push(cleaned);
                        }
                    } else {
                        const matches = cleaned.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g);
                        if (matches) {
                            matches.forEach(m => {
                                const norm = m.toLowerCase();
                                if (!seen.has(norm)) {
                                    seen.add(norm);
                                    extractedEmails.push(m);
                                }
                            });
                        }
                    }
                });
            }
        }

        // Fallback global regex scan if no emails found yet
        if (extractedEmails.length === 0) {
            const globalMatches = text.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g);
            if (globalMatches) {
                globalMatches.forEach(m => {
                    const norm = m.toLowerCase();
                    if (!seen.has(norm)) {
                        seen.add(norm);
                        extractedEmails.push(m);
                    }
                });
            }
        }

        if (extractedEmails.length === 0) {
            alert("No valid email addresses could be detected in this file.");
            return;
        }

        lastParsedCsvEmails = extractedEmails;
        applyParsedEmailsToTextarea(extractedEmails, fileName, lines.length);
    }

    function applyParsedEmailsToTextarea(emails, fileName, totalRows) {
        const ta = document.getElementById('pasted-emails');
        if (!ta) return;

        if (importMode === 'append' && ta.value.trim().length > 0) {
            const existing = ta.value.trim().split(/[\n,]+/).map(e => e.trim()).filter(e => e.length > 0);
            const combinedSet = new Set(existing.map(e => e.toLowerCase()));
            const toAdd = [];
            emails.forEach(e => {
                if (!combinedSet.has(e.toLowerCase())) {
                    combinedSet.add(e.toLowerCase());
                    toAdd.push(e);
                }
            });
            ta.value = existing.join('\n') + (toAdd.length > 0 ? '\n' + toAdd.join('\n') : '');
        } else {
            ta.value = emails.join('\n');
        }

        const statusCard = document.getElementById('csv-status-card');
        const statusFileName = document.getElementById('csv-status-filename');
        const statusCountBadge = document.getElementById('csv-status-count-badge');
        const statusDetails = document.getElementById('csv-status-details');

        if (statusCard) {
            statusCard.classList.remove('d-none');
            if (statusFileName) statusFileName.textContent = fileName || 'Uploaded File';
            if (statusCountBadge) statusCountBadge.textContent = `${emails.length} Emails Extracted`;
            if (statusDetails) statusDetails.textContent = `Parsed ${totalRows || emails.length} rows. Cleaned and deduplicated.`;
        }

        updatePastedCount();
    }

    function setImportMode(mode) {
        importMode = mode;
        const btnReplace = document.getElementById('btn-import-replace');
        const btnAppend = document.getElementById('btn-import-append');
        if (btnReplace && btnAppend) {
            if (mode === 'replace') {
                btnReplace.classList.add('active');
                btnAppend.classList.remove('active');
            } else {
                btnAppend.classList.add('active');
                btnReplace.classList.remove('active');
            }
        }
        if (lastParsedCsvEmails.length > 0) {
            applyParsedEmailsToTextarea(lastParsedCsvEmails, currentCsvFileName, currentCsvTotalRows);
        }
    }

    function clearCsvImport() {
        lastParsedCsvEmails = [];
        currentCsvFileName = '';
        currentCsvTotalRows = 0;
        const input = document.getElementById('csv-file-input');
        if (input) input.value = '';
        const statusCard = document.getElementById('csv-status-card');
        if (statusCard) statusCard.classList.add('d-none');
    }

    function clearPastedList() {
        const ta = document.getElementById('pasted-emails');
        if (ta) {
            ta.value = '';
            updatePastedCount();
        }
        clearCsvImport();
    }

    function cleanAndDeduplicatePasted() {
        const ta = document.getElementById('pasted-emails');
        if (!ta || !ta.value.trim()) return;
        const emails = ta.value.split(/[\n,;\t]+/).map(e => e.trim()).filter(e => e.length > 0 && e.includes('@'));
        const seen = new Set();
        const cleaned = [];
        emails.forEach(e => {
            const norm = e.toLowerCase();
            if (!seen.has(norm)) {
                seen.add(norm);
                cleaned.push(e);
            }
        });
        ta.value = cleaned.join('\n');
        updatePastedCount();
    }

    function uploadCsvToServer(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('_token', '{{ csrf_token() }}');

        fetch('/api/campaign/parse-csv', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.emails) {
                lastParsedCsvEmails = data.emails;
                currentCsvFileName = data.filename;
                currentCsvTotalRows = data.total_rows;
                applyParsedEmailsToTextarea(data.emails, data.filename, data.total_rows);
            } else {
                alert(data.message || "Failed to parse CSV file.");
            }
        })
        .catch(err => {
            console.error("CSV upload error:", err);
            alert("Error uploading CSV file.");
        });
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
        const list = document.getElementById('recipient-list');
        fetch('/api/salesforce/recipients')
            .then(res => {
                if (!res.ok) {
                    throw new Error(`Server returned HTTP ${res.status}`);
                }
                return res.json();
            })
            .then(records => {
                if (!Array.isArray(records)) {
                    throw new Error(records.error || records.message || "Invalid data format received");
                }
                allRecords = records || [];
                populateFilterPicklists(allRecords);
                renderRecipients(allRecords);
            })
            .catch(err => {
                console.error("Error loading recipients:", err);
                if (list) {
                    list.innerHTML = `<li class="list-group-item text-center text-danger py-4" style="font-size: 0.8125rem;"><i class="bi bi-exclamation-triangle me-1"></i> Failed to load Salesforce records: ${escapeHtml(err.message)}</li>`;
                }
            });
    }

    const DEAL_CATEGORIES = @json($dealCategories);

    function populateFilterPicklists(records) {
        // Keep track of currently checked values
        const prevCategories = new Set(Array.from(document.querySelectorAll('.filter-category-checkbox:checked')).map(cb => cb.value));
        const prevRegions = new Set(Array.from(document.querySelectorAll('.filter-region-checkbox:checked')).map(cb => cb.value));
        const prevCountries = new Set(Array.from(document.querySelectorAll('.filter-country-checkbox:checked')).map(cb => cb.value));

        // 1. Categories (Fixed Deal Categories)
        const catMenu = document.getElementById('menu-filter-category');
        if (catMenu) {
            let catHtml = '';
            DEAL_CATEGORIES.forEach((cat, idx) => {
                const isChecked = prevCategories.has(cat) ? 'checked' : '';
                catHtml += `
                    <div class="form-check py-1 px-3 d-flex align-items-center gap-2 rounded-2">
                        <input class="form-check-input filter-category-checkbox mt-0" type="checkbox" value="${escapeHtml(cat)}" id="cat-${idx}" ${isChecked} onchange="filterRecipients()">
                        <label class="form-check-label text-zinc-800" for="cat-${idx}" style="font-size: 0.8125rem; font-weight: 500; cursor: pointer;">${escapeHtml(cat)}</label>
                    </div>
                `;
            });
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
        const activeCategories = Array.from(document.querySelectorAll('.filter-category-checkbox:checked')).map(cb => cb.value.trim().toLowerCase());
        if (activeCategories.length > 0) {
            filtered = filtered.filter(r => {
                if (!r.deal_category) return false;
                const recCat = r.deal_category.trim().toLowerCase();
                return activeCategories.includes(recCat);
            });
            const checkedLabels = Array.from(document.querySelectorAll('.filter-category-checkbox:checked')).map(cb => cb.value);
            document.getElementById('label-filter-category').innerText = checkedLabels.length <= 2 
                ? checkedLabels.join(', ') 
                : `${checkedLabels.length} selected`;
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

        const showEmail = true;
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

            const emailText = r.email ? ` &bull; <span class="text-indigo-600 font-monospace" style="font-size: 0.775rem;">${escapeHtml(r.email)}</span>` : '';
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
        const replyToEl = document.getElementById('reply-to');
        const reply_to = (replyToEl && replyToEl.value.trim()) ? replyToEl.value.trim() : 'support@b2bexportsllc.com';
        const body = getCampaignBodyContent();
        const scheduled_at = document.getElementById('schedule-datetime').value || null;
        const templateId = document.getElementById('template-select').value || null;
        const signatureId = document.getElementById('signature-select').value || null;

        if (!subject || !body) {
            alert("Please provide both subject and body for the email.");
            return;
        }

        const payload = {
            subject,
            sending_domain,
            from_address,
            reply_to,
            body,
            attachments: attachedFilesList,
            scheduled_at: scheduled_at,
            template_id: templateId,
            signature_id: signatureId
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
                if (data.status === 'pending_approval') {
                    let msg = '✅ Campaign submitted successfully!\n\n'
                        + '⏳ PENDING MANAGER APPROVAL\n\n'
                        + 'Your campaign has been placed on hold. No emails will be sent until your manager reviews and approves it.\n\n'
                        + 'You will be redirected to the campaign status page where you can track its approval progress.';

                    if (data.warning) {
                        msg += '\n\n⚠️ WARNING: ' + data.warning;
                    }

                    alert(msg);
                }
                window.location.href = `/campaign/${data.campaign_id}`;
            } else {
                alert(data.error || "Failed to submit campaign.");
            }
        })
        .catch(err => console.error("Error creating campaign:", err));
    }

    // --- Dynamic Personalization Merge Fields ---
    function insertMergeField(fieldTag) {
        if (currentEditorMode === 'html') {
            const ta = document.getElementById('html-source-editor');
            if (ta) {
                const start = ta.selectionStart || 0;
                const end = ta.selectionEnd || 0;
                const val = ta.value;
                ta.value = val.substring(0, start) + fieldTag + val.substring(end);
                ta.selectionStart = ta.selectionEnd = start + fieldTag.length;
                ta.focus();
            }
        } else if (currentEditorMode === 'preview') {
            setEditorMode('html');
            insertMergeField(fieldTag);
        } else {
            if (!quill) return;
            quill.focus();
            const range = quill.getSelection(true);
            const index = range ? range.index : quill.getLength();
            quill.insertText(index, fieldTag, 'user');
            quill.setSelection(index + fieldTag.length, 'user');
        }
    }

    // --- Template Management ---
    let savedTemplates = [];
    let savedSignatures = [];

    function loadTemplates() {
        fetch('/api/templates')
            .then(res => res.json())
            .then(data => {
                savedTemplates = data || [];
                const select = document.getElementById('template-select');
                select.innerHTML = '<option value="">-- No Template Selected --</option>';
                savedTemplates.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = t.name + (t.is_default ? ' (Default)' : '');
                    select.appendChild(opt);
                });
                onTemplateSelectionChange();
            })
            .catch(err => console.error("Error loading templates:", err));
    }

    function onTemplateSelectionChange() {
        const id = document.getElementById('template-select').value;
        const hasSelection = Boolean(id);
        const btnApply = document.getElementById('btn-apply-template');
        const btnPreview = document.getElementById('btn-preview-template');
        const btnEdit = document.getElementById('btn-edit-template');
        const btnDelete = document.getElementById('btn-delete-template');

        if (btnApply) btnApply.disabled = !hasSelection;
        if (btnPreview) btnPreview.disabled = !hasSelection;
        if (btnEdit) btnEdit.disabled = !hasSelection;
        if (btnDelete) btnDelete.disabled = !hasSelection;
    }

    function loadSelectedTemplate() {
        const select = document.getElementById('template-select');
        const id = select.value;
        if (!id) return;
        const tmpl = savedTemplates.find(t => t.id == id);
        if (tmpl) {
            document.getElementById('subject').value = tmpl.subject || '';
            const bodyContent = tmpl.body || '';

            // Store unmodified pristine HTML in the HTML source editor
            const htmlEditor = document.getElementById('html-source-editor');
            if (htmlEditor) {
                htmlEditor.value = bodyContent;
            }

            // Check if template contains HTML tables or rich marketing layout
            const isTableTemplate = /<table|<tbody|<tr|<td|<style|<div style=|max-width/i.test(bodyContent);

            if (isTableTemplate) {
                // DO NOT feed complex tables into Quill, as Quill strips/mutates nested tables!
                // Switch directly to HTML Source mode to keep the template 100% intact:
                setEditorMode('html');
                const prevBox = document.getElementById('editor-live-preview-box');
                if (prevBox) prevBox.innerHTML = bodyContent;
            } else {
                if (quill) {
                    quill.root.innerHTML = bodyContent;
                }
                setEditorMode('visual');
            }
        }
    }

    function previewSelectedTemplate() {
        const id = document.getElementById('template-select').value;
        if (!id) return;
        const tmpl = savedTemplates.find(t => t.id == id);
        if (tmpl) {
            document.getElementById('prev_tmpl_name').textContent = tmpl.name;
            document.getElementById('prev_tmpl_subject').textContent = tmpl.subject;
            document.getElementById('prev_tmpl_body').innerHTML = tmpl.body;
            const modalEl = document.getElementById('templatePreviewModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }
        }
    }

    function applyPreviewedTemplate() {
        loadSelectedTemplate();
        const modalEl = document.getElementById('templatePreviewModal');
        if (modalEl) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    }

    function openCreateTemplateModal() {
        document.getElementById('tmpl_modal_id').value = '';
        document.getElementById('templateModalLabel').innerHTML = '<i class="bi bi-journal-bookmark-fill text-primary me-2"></i> Create Email Template';
        document.getElementById('tmpl_modal_name').value = '';
        document.getElementById('tmpl_modal_subject').value = (document.getElementById('subject') ? document.getElementById('subject').value : '');
        document.getElementById('tmpl_modal_body').value = getCampaignBodyContent();
        
        const modalEl = document.getElementById('templateModal');
        if (modalEl) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }

    function openEditTemplateModal() {
        const id = document.getElementById('template-select').value;
        if (!id) return;
        const tmpl = savedTemplates.find(t => t.id == id);
        if (!tmpl) return;

        document.getElementById('tmpl_modal_id').value = tmpl.id;
        document.getElementById('templateModalLabel').innerHTML = '<i class="bi bi-pencil-square text-primary me-2"></i> Edit Email Template';
        document.getElementById('tmpl_modal_name').value = tmpl.name;
        document.getElementById('tmpl_modal_subject').value = tmpl.subject;
        document.getElementById('tmpl_modal_body').value = tmpl.body;

        const modalEl = document.getElementById('templateModal');
        if (modalEl) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }

    function copyEditorContentToTemplateModal() {
        document.getElementById('tmpl_modal_subject').value = (document.getElementById('subject') ? document.getElementById('subject').value : '');
        document.getElementById('tmpl_modal_body').value = getCampaignBodyContent();
    }

    function saveTemplateFromModal() {
        const id = document.getElementById('tmpl_modal_id').value;
        const name = document.getElementById('tmpl_modal_name').value.trim();
        const subject = document.getElementById('tmpl_modal_subject').value.trim();
        const body = document.getElementById('tmpl_modal_body').value.trim();

        if (!name || !subject || !body) {
            alert("Name, Subject, and Body are all required.");
            return;
        }

        const url = id ? `/api/templates/${id}` : '/api/templates';
        const method = id ? 'PUT' : 'POST';

        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, subject, body })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const modalEl = document.getElementById('templateModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                loadTemplates();
                setTimeout(() => {
                    const targetId = data.template_id || (data.template && data.template.id);
                    if (targetId) {
                        const select = document.getElementById('template-select');
                        if (select) {
                            select.value = targetId;
                            onTemplateSelectionChange();
                        }
                    }
                }, 400);
            } else {
                alert(data.error || "Failed to save template.");
            }
        })
        .catch(err => alert("Error saving template: " + err.message));
    }

    function saveAsTemplate() {
        openCreateTemplateModal();
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

    // --- Signatures Management ---
    function loadSignatures() {
        fetch('/api/signatures')
            .then(res => res.json())
            .then(data => {
                savedSignatures = data || [];
                const select = document.getElementById('signature-select');
                select.innerHTML = '<option value="">-- No Signature Selected --</option>';
                let defaultSigId = null;

                savedSignatures.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.name + (s.is_default ? ' (Default)' : '');
                    if (s.is_default) {
                        defaultSigId = s.id;
                    }
                    select.appendChild(opt);
                });

                if (defaultSigId) {
                    select.value = defaultSigId;
                }
                onSignatureSelectionChange();
            })
            .catch(err => console.error("Error loading signatures:", err));
    }

    function onSignatureSelectionChange() {
        const id = document.getElementById('signature-select').value;
        const hasSelection = Boolean(id);
        const btnInsert = document.getElementById('btn-insert-sig');
        const btnPreview = document.getElementById('btn-preview-sig');
        if (btnInsert) btnInsert.disabled = !hasSelection;
        if (btnPreview) btnPreview.disabled = !hasSelection;
    }

    function previewSelectedSignature() {
        const id = document.getElementById('signature-select').value;
        if (!id) return;
        const sig = savedSignatures.find(s => s.id == id);
        if (sig) {
            document.getElementById('prev_sig_name').textContent = sig.name;
            document.getElementById('prev_sig_body').innerHTML = sig.rendered_html || sig.content || '';
            const modalEl = document.getElementById('signaturePreviewModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }
        }
    }

    function insertSelectedSignature() {
        const id = document.getElementById('signature-select').value;
        if (!id) return;
        insertMergeField('@{{Signature}}');
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

    // --- Live Personalized Email Preview ---
    function openLivePreviewModal() {
        const subject = document.getElementById('subject').value || '(No Subject)';
        const body = getCampaignBodyContent();
        const signatureId = document.getElementById('signature-select').value || null;

        const recSelect = document.getElementById('prev_recipient_select');
        recSelect.innerHTML = '';

        if (selectedRecords.size > 0) {
            Array.from(selectedRecords).forEach(id => {
                const rec = allRecords.find(r => r.id == id);
                if (rec) {
                    const opt = document.createElement('option');
                    opt.value = rec.id;
                    const rName = rec.name || (rec.first_name + ' ' + rec.last_name);
                    opt.textContent = `${rName} (${rec.email}) [${rec.object_type || 'CRM'}]`;
                    recSelect.appendChild(opt);
                }
            });
        }

        if (recSelect.options.length === 0) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'Demo Contact: John Doe (john.doe@example.com)';
            recSelect.appendChild(opt);
        }

        refreshLivePreviewModal();
        const modalEl = document.getElementById('campaignPreviewModal');
        if (modalEl) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }

    function refreshLivePreviewModal() {
        const subject = document.getElementById('subject').value || '(No Subject)';
        const body = getCampaignBodyContent();
        const signatureId = document.getElementById('signature-select').value || null;
        const recipientId = document.getElementById('prev_recipient_select').value || null;

        document.getElementById('prev_from_field').textContent = document.getElementById('from-address').value || 'rma@proitbuyer.com';
        const replyToVal = (document.getElementById('reply-to') ? document.getElementById('reply-to').value.trim() : '') || 'support@b2bexportsllc.com';
        const prevReplyTo = document.getElementById('prev_reply_to_field');
        if (prevReplyTo) {
            prevReplyTo.textContent = replyToVal;
        }

        fetch('/api/campaign/preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                subject: subject,
                body: body,
                signature_id: signatureId,
                recipient_id: recipientId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('prev_subject_field').textContent = data.resolved_subject;
                document.getElementById('prev_body_field').innerHTML = data.resolved_body;
                document.getElementById('prev_to_field').textContent = (data.recipient.full_name || 'Recipient') + ' <' + (data.recipient.email || '') + '>';
                document.getElementById('prev_rec_email').textContent = data.recipient.email || 'N/A';
                document.getElementById('prev_rec_company').textContent = data.recipient.company || 'N/A';
            }
        })
        .catch(err => console.error("Preview error:", err));
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

        if (recipientMethod === 'paste') {
            updatePastedCount();
        }

        initCsvDropzone();

        // Prevent closing of bootstrap dropdowns when clicking checkboxes inside them
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        });
    };
</script>
@endsection
