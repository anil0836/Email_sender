@extends('layouts.app')

@section('page_title', 'Salesforce Leads')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h4 fw-bold text-zinc-900 mb-0">Salesforce Leads</h1>
                <span class="badge bg-primary-soft" id="lead-count-badge">
                    <span class="spinner-grow spinner-grow-sm me-1" role="status" style="width: 0.55rem; height: 0.55rem;"></span> Connecting...
                </span>
            </div>
            <p class="text-secondary small mb-0">
                Live Lead records retrieved directly from the connected Salesforce organization via JSforce.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <!-- Search Filter -->
            <div class="input-group input-group-sm" style="max-width: 260px;">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" id="lead-search-input" class="form-control border-start-0 ps-0" placeholder="Search name, company, email..." onkeydown="if(event.key==='Enter') applySearch()">
                <button class="btn btn-outline-secondary border-start-0" type="button" id="lead-search-clear-btn" style="display: none;" onclick="clearSearch()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Records per page dropdown -->
            <div class="d-flex align-items-center gap-1.5">
                <label for="per-page-select" class="small text-secondary text-nowrap d-none d-sm-inline">Show:</label>
                <select id="per-page-select" class="form-select form-select-sm" style="width: 85px;" onchange="changePerPage(this.value)">
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>

            <!-- Test Connection Button -->
            <a href="{{ route('salesforce.connection_test') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1.5 shadow-sm" title="Test Salesforce API Connection">
                <i class="bi bi-shield-check text-primary"></i>
                <span class="d-none d-md-inline">Test Connection</span>
            </a>

            <!-- Refresh Button -->
            <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1.5 shadow-sm" id="refresh-btn" onclick="fetchLeads()">
                <i class="bi bi-arrow-clockwise" id="refresh-icon"></i>
                <span>Refresh</span>
            </button>
        </div>
    </div>

    <!-- Error Alert State Container -->
    <div id="error-container" class="mb-4" style="display: none;">
        <div class="alert alert-danger border-danger-subtle d-flex align-items-start gap-3 p-3 shadow-sm rounded-3">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5 mt-0.5"></i>
            <div class="flex-grow-1">
                <h6 class="alert-heading fw-semibold mb-1" id="error-title">Salesforce Communication Error</h6>
                <p class="small text-danger-emphasis mb-2" id="error-message">Unable to retrieve Lead records from Salesforce.</p>
                <div id="error-troubleshooting" class="small text-muted bg-white bg-opacity-75 p-2.5 rounded border border-danger-subtle mb-2 d-none">
                    <strong>Troubleshooting Suggestions:</strong>
                    <ul class="mb-0 ps-3 mt-1" id="error-troubleshooting-list">
                    </ul>
                </div>
                <button class="btn btn-sm btn-danger shadow-sm" onclick="fetchLeads()">
                    <i class="bi bi-arrow-repeat me-1"></i> Retry Connection
                </button>
            </div>
        </div>
    </div>

    <!-- Main Card Container -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-semibold text-zinc-900" style="font-size: 0.875rem;">
                    <i class="bi bi-person-lines-fill text-primary me-1.5"></i> Salesforce Lead Directory
                </span>
                <span class="text-zinc-400">&bull;</span>
                <span class="small text-secondary" id="results-summary">
                    Loading records...
                </span>
            </div>

            <!-- Search Indicator Badge -->
            <div id="active-search-badge" class="badge bg-secondary-soft d-none align-items-center gap-1.5 py-1 px-2">
                <span class="text-secondary">Filter:</span>
                <strong id="active-search-text" class="text-dark"></strong>
                <button type="button" class="btn-close ms-1" style="font-size: 0.55rem;" onclick="clearSearch()" aria-label="Clear filter"></button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="leads-table">
                    <thead>
                        <tr>
                            <th class="ps-4" style="min-width: 180px;">Lead Name</th>
                            <th style="min-width: 160px;">Company</th>
                            <th style="min-width: 180px;">Email</th>
                            <th style="min-width: 130px;">Phone</th>
                            <th style="min-width: 130px;">Mobile</th>
                            <th style="min-width: 120px;">Status</th>
                            <th style="min-width: 130px;">Lead Source</th>
                            <th class="pe-4 text-end" style="min-width: 140px;">Created Date</th>
                        </tr>
                    </thead>
                    <tbody id="leads-table-body">
                        <!-- Loading State Row -->
                        <tr id="loading-row">
                            <td colspan="8" class="text-center py-5">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                <span class="text-secondary fw-medium">Connecting to Salesforce and querying Lead records...</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State Placeholder -->
            <div id="empty-state" class="text-center py-5 px-3 d-none">
                <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 56px; height: 56px;">
                    <i class="bi bi-person-x text-muted fs-3"></i>
                </div>
                <h6 class="fw-semibold text-zinc-800 mb-1">No Salesforce Leads Found</h6>
                <p class="text-secondary small mb-3" id="empty-state-text">
                    There are no Lead records in your Salesforce organization matching the current criteria.
                </p>
                <button class="btn btn-outline-secondary btn-sm" onclick="fetchLeads()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh Table
                </button>
            </div>
        </div>

        <!-- Footer with Pagination -->
        <div class="card-footer bg-white border-top py-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
            <div class="small text-secondary" id="pagination-info">
                Showing 0 to 0 of 0 leads
            </div>

            <nav aria-label="Salesforce Leads Pagination" id="pagination-container" class="d-none">
                <ul class="pagination pagination-sm mb-0 gap-1" id="pagination-list">
                    <!-- Dynamic Pagination Items -->
                </ul>
            </nav>
        </div>
    </div>
</div>
@endsection

@section('extra_scripts')
<script>
    // State Management
    let currentPage = 1;
    let perPage = 25;
    let searchQuery = '';
    let isFetching = false;

    /**
     * Fetch Leads from the secure backend API endpoint
     */
    async function fetchLeads(page = currentPage) {
        if (isFetching) return;
        isFetching = true;
        currentPage = page;

        // UI Loading State
        const refreshIcon = document.getElementById('refresh-icon');
        if (refreshIcon) refreshIcon.classList.add('bi-spin');

        const tbody = document.getElementById('leads-table-body');
        const emptyState = document.getElementById('empty-state');
        const errorContainer = document.getElementById('error-container');
        const paginationContainer = document.getElementById('pagination-container');
        const resultsSummary = document.getElementById('results-summary');
        const leadCountBadge = document.getElementById('lead-count-badge');

        errorContainer.style.display = 'none';
        emptyState.classList.add('d-none');
        tbody.innerHTML = `
            <tr id="loading-row">
                <td colspan="8" class="text-center py-5">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    <span class="text-secondary fw-medium">Connecting to Salesforce and querying Lead records...</span>
                </td>
            </tr>
        `;
        resultsSummary.innerText = 'Querying Salesforce...';

        try {
            const url = new URL('/api/salesforce/leads', window.location.origin);
            url.searchParams.set('page', currentPage);
            url.searchParams.set('per_page', perPage);
            if (searchQuery) {
                url.searchParams.set('search', searchQuery);
            }

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!response.ok || data.success === false) {
                handleFetchError(data);
                return;
            }

            renderLeadsTable(data);
        } catch (err) {
            handleFetchError({
                error: 'Network or client error: Failed to contact the local Salesforce bridge layer.',
                errorCode: 'NETWORK_ERROR'
            });
        } finally {
            isFetching = false;
            if (refreshIcon) refreshIcon.classList.remove('bi-spin');
        }
    }

    /**
     * Render the table with retrieved Salesforce Lead records
     */
    function renderLeadsTable(data) {
        const tbody = document.getElementById('leads-table-body');
        const emptyState = document.getElementById('empty-state');
        const leadCountBadge = document.getElementById('lead-count-badge');
        const resultsSummary = document.getElementById('results-summary');
        const paginationInfo = document.getElementById('pagination-info');
        const paginationContainer = document.getElementById('pagination-container');

        const total = data.total || 0;
        const records = data.records || [];
        const limit = data.limit || perPage;
        const offset = data.offset || 0;
        const totalPages = data.totalPages || 1;

        // Update Total Badge
        leadCountBadge.innerHTML = `<i class="bi bi-lightning-charge-fill me-1"></i> Total Leads: <strong>${total.toLocaleString()}</strong>`;
        leadCountBadge.className = 'badge bg-primary-soft';

        if (records.length === 0) {
            tbody.innerHTML = '';
            emptyState.classList.remove('d-none');
            const emptyText = document.getElementById('empty-state-text');
            if (searchQuery) {
                emptyText.innerText = `No leads found matching "${searchQuery}". Try clearing your search filter.`;
            } else {
                emptyText.innerText = 'No Lead records are currently available in this Salesforce organization.';
            }
            resultsSummary.innerText = '0 records found';
            paginationInfo.innerText = 'Showing 0 to 0 of 0 leads';
            paginationContainer.classList.add('d-none');
            return;
        }

        emptyState.classList.add('d-none');

        const startRecord = offset + 1;
        const endRecord = Math.min(offset + records.length, total);
        resultsSummary.innerText = `${records.length} records on this page`;
        paginationInfo.innerText = `Showing ${startRecord.toLocaleString()} to ${endRecord.toLocaleString()} of ${total.toLocaleString()} leads`;

        let html = '';
        records.forEach(lead => {
            const statusBadge = getStatusBadge(lead.Status);
            const formattedDate = formatDate(lead.CreatedDate);
            const leadName = lead.Name || `${lead.FirstName || ''} ${lead.LastName || ''}`.trim() || 'Unnamed Lead';

            html += `
                <tr data-id="${escapeHtml(lead.Id)}" class="lead-row">
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-sm rounded-circle bg-light border d-flex align-items-center justify-content-center text-primary fw-bold" style="width: 32px; height: 32px; font-size: 0.75rem; flex-shrink: 0;">
                                ${escapeHtml(leadName.charAt(0).toUpperCase())}
                            </div>
                            <div>
                                <div class="fw-semibold text-zinc-900">${escapeHtml(leadName)}</div>
                                <div class="text-secondary small text-truncate" style="max-width: 190px;" title="${escapeHtml(lead.Title || '')}">
                                    ${lead.Title ? escapeHtml(lead.Title) : '<span class="text-zinc-400 fst-italic">No title</span>'}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-medium text-zinc-800 text-truncate" style="max-width: 170px;" title="${escapeHtml(lead.Company || '')}">
                            ${lead.Company ? escapeHtml(lead.Company) : '<span class="text-zinc-400 fst-italic">-</span>'}
                        </div>
                    </td>
                    <td>
                        ${lead.Email ? `
                            <a href="mailto:${escapeHtml(lead.Email)}" class="text-decoration-none text-zinc-900 fw-medium d-inline-flex align-items-center gap-1 text-truncate" style="max-width: 190px;" title="${escapeHtml(lead.Email)}">
                                <i class="bi bi-envelope text-zinc-400"></i> ${escapeHtml(lead.Email)}
                            </a>
                        ` : '<span class="text-zinc-400 fst-italic">-</span>'}
                    </td>
                    <td>
                        ${lead.Phone ? `
                            <span class="text-zinc-800 d-inline-flex align-items-center gap-1 font-monospace" style="font-size: 0.8rem;">
                                <i class="bi bi-telephone text-zinc-400"></i> ${escapeHtml(lead.Phone)}
                            </span>
                        ` : '<span class="text-zinc-400 fst-italic">-</span>'}
                    </td>
                    <td>
                        ${lead.MobilePhone ? `
                            <span class="text-zinc-800 d-inline-flex align-items-center gap-1 font-monospace" style="font-size: 0.8rem;">
                                <i class="bi bi-phone text-zinc-400"></i> ${escapeHtml(lead.MobilePhone)}
                            </span>
                        ` : '<span class="text-zinc-400 fst-italic">-</span>'}
                    </td>
                    <td>
                        ${statusBadge}
                    </td>
                    <td>
                        <span class="text-secondary small">
                            ${lead.LeadSource ? escapeHtml(lead.LeadSource) : '<span class="text-zinc-400 fst-italic">-</span>'}
                        </span>
                    </td>
                    <td class="pe-4 text-end">
                        <span class="text-secondary small text-nowrap" title="${escapeHtml(lead.CreatedDate || '')}">
                            ${formattedDate}
                        </span>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;

        // Render Pagination
        renderPagination(currentPage, totalPages);
    }

    /**
     * Render the pagination navigation bar
     */
    function renderPagination(current, totalPages) {
        const paginationContainer = document.getElementById('pagination-container');
        const paginationList = document.getElementById('pagination-list');

        if (totalPages <= 1) {
            paginationContainer.classList.add('d-none');
            return;
        }

        paginationContainer.classList.remove('d-none');
        let html = '';

        // Previous button
        html += `
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <button class="page-link" onclick="fetchLeads(${current - 1})" aria-label="Previous">
                    <i class="bi bi-chevron-left"></i>
                </button>
            </li>
        `;

        // Page Numbers with smart ellipsis
        const delta = 2;
        const left = current - delta;
        const right = current + delta + 1;
        let range = [];
        let l;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= left && i < right)) {
                range.push(i);
            }
        }

        for (let i of range) {
            if (l) {
                if (i - l === 2) {
                    range.push(l + 1);
                } else if (i - l !== 1) {
                    html += `<li class="page-item disabled"><span class="page-link">&hellip;</span></li>`;
                }
            }
            html += `
                <li class="page-item ${i === current ? 'active' : ''}">
                    <button class="page-link" onclick="fetchLeads(${i})">${i}</button>
                </li>
            `;
            l = i;
        }

        // Next button
        html += `
            <li class="page-item ${current === totalPages ? 'disabled' : ''}">
                <button class="page-link" onclick="fetchLeads(${current + 1})" aria-label="Next">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </li>
        `;

        paginationList.innerHTML = html;
    }

    /**
     * Handle and display errors with graceful feedback
     */
    function handleFetchError(data) {
        const tbody = document.getElementById('leads-table-body');
        const emptyState = document.getElementById('empty-state');
        const errorContainer = document.getElementById('error-container');
        const errorTitle = document.getElementById('error-title');
        const errorMessage = document.getElementById('error-message');
        const troubleshooting = document.getElementById('error-troubleshooting');
        const troubleshootingList = document.getElementById('error-troubleshooting-list');
        const leadCountBadge = document.getElementById('lead-count-badge');
        const resultsSummary = document.getElementById('results-summary');
        const paginationContainer = document.getElementById('pagination-container');

        tbody.innerHTML = '';
        emptyState.classList.add('d-none');
        paginationContainer.classList.add('d-none');
        resultsSummary.innerText = 'Error occurred';
        leadCountBadge.innerHTML = `<i class="bi bi-exclamation-circle-fill me-1"></i> Connection Error`;
        leadCountBadge.className = 'badge bg-danger-soft';

        errorTitle.innerText = data.errorCode ? `Salesforce Error (${data.errorCode})` : 'Salesforce Communication Error';
        errorMessage.innerText = data.error || 'Failed to retrieve Lead records from Salesforce.';

        // Provide helpful contextual troubleshooting suggestions
        troubleshootingList.innerHTML = '';
        if (data.errorCode === 'CREDENTIALS_MISSING') {
            troubleshootingList.innerHTML = `
                <li>Open your <code>.env</code> file in the project root.</li>
                <li>Set <code>SF_USERNAME=your_salesforce_username</code>.</li>
                <li>Set <code>SF_PASSWORD=your_password_and_token</code> (append your Salesforce Security Token directly to your password).</li>
                <li>Ensure <code>SF_LOGIN_URL=https://login.salesforce.com</code> (or your custom MyDomain login URL).</li>
            `;
            troubleshooting.classList.remove('d-none');
        } else if ((data.error || '').includes('Security Token') || (data.error || '').includes('INVALID_LOGIN')) {
            troubleshootingList.innerHTML = `
                <li>Verify your Salesforce username and password are correct.</li>
                <li>If your Salesforce org enforces API login security tokens, append your Security Token directly to <code>SF_PASSWORD</code> (e.g. <code>mypasswordXXXXtoken</code>).</li>
                <li>Check if your user account is locked or password expired in Salesforce.</li>
                <li>Verify if IP login restrictions or Login Hours are enabled in your Salesforce Profile.</li>
            `;
            troubleshooting.classList.remove('d-none');
        } else {
            troubleshooting.classList.add('d-none');
        }

        errorContainer.style.display = 'block';
    }

    /**
     * Get soft colored status badges based on standard Salesforce Lead statuses
     */
    function getStatusBadge(status) {
        if (!status) return '<span class="badge bg-secondary-soft text-secondary">Unknown</span>';
        const st = status.toLowerCase();

        if (st.includes('qualified') || st.includes('converted') || st.includes('closed - converted')) {
            return `<span class="badge bg-success-soft"><i class="bi bi-check-circle me-1"></i>${escapeHtml(status)}</span>`;
        }
        if (st.includes('working') || st.includes('contacted') || st.includes('in progress')) {
            return `<span class="badge bg-primary-soft"><i class="bi bi-clock-history me-1"></i>${escapeHtml(status)}</span>`;
        }
        if (st.includes('open') || st.includes('new') || st.includes('unassigned')) {
            return `<span class="badge bg-warning-soft"><i class="bi bi-sun me-1"></i>${escapeHtml(status)}</span>`;
        }
        if (st.includes('unqualified') || st.includes('lost') || st.includes('junk') || st.includes('dead') || st.includes('closed - not converted')) {
            return `<span class="badge bg-danger-soft"><i class="bi bi-x-circle me-1"></i>${escapeHtml(status)}</span>`;
        }
        return `<span class="badge bg-secondary-soft"><i class="bi bi-tag me-1"></i>${escapeHtml(status)}</span>`;
    }

    /**
     * Format Salesforce ISO timestamp into readable date format
     */
    function formatDate(dateStr) {
        if (!dateStr) return '<span class="text-zinc-400 fst-italic">-</span>';
        try {
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return escapeHtml(dateStr);
            return d.toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }) + ' ' + d.toLocaleTimeString(undefined, {
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return escapeHtml(dateStr);
        }
    }

    /**
     * Sanitize HTML entities to prevent XSS
     */
    function escapeHtml(text) {
        if (!text && text !== 0) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    /**
     * Search and Pagination Controls Handlers
     */
    function applySearch() {
        const input = document.getElementById('lead-search-input');
        searchQuery = input.value.trim();
        const activeSearchBadge = document.getElementById('active-search-badge');
        const activeSearchText = document.getElementById('active-search-text');
        const clearBtn = document.getElementById('lead-search-clear-btn');

        if (searchQuery) {
            activeSearchBadge.classList.remove('d-none');
            activeSearchBadge.classList.add('d-inline-flex');
            activeSearchText.innerText = searchQuery;
            clearBtn.style.display = 'inline-block';
        } else {
            activeSearchBadge.classList.add('d-none');
            activeSearchBadge.classList.remove('d-inline-flex');
            clearBtn.style.display = 'none';
        }

        fetchLeads(1);
    }

    function clearSearch() {
        const input = document.getElementById('lead-search-input');
        input.value = '';
        searchQuery = '';
        document.getElementById('active-search-badge').classList.add('d-none');
        document.getElementById('active-search-badge').classList.remove('d-inline-flex');
        document.getElementById('lead-search-clear-btn').style.display = 'none';
        fetchLeads(1);
    }

    function changePerPage(val) {
        perPage = parseInt(val, 10) || 25;
        fetchLeads(1);
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        fetchLeads(1);
    });
</script>

<style>
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .bi-spin {
        animation: spin 1s linear infinite;
        display: inline-block;
    }
    .lead-row:hover {
        background-color: #f8fafc !important;
    }
</style>
@endsection
