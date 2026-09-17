@extends('layouts.app')

@section('page_title', 'Infrastructure Management')

@section('content')
<div class="row g-4">
    <!-- Server Configurations and Sending IPs -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-zinc-900"><i class="bi bi-hdd-network me-1.5 text-zinc-500"></i> Outbound Sending Servers & Dedicated IPs</span>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addServerModal">
                    <i class="bi bi-plus"></i> Add Server
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive border-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Server Name</th>
                                <th>SMTP Host</th>
                                <th>Sending IP</th>
                                <th>Status</th>
                                <th class="text-center pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="servers-table-body">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Loading servers...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Approved Sending Domains -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-zinc-900"><i class="bi bi-globe me-1.5 text-zinc-500"></i> Approved Sending Domains & Authentication</span>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDomainModal">
                    <i class="bi bi-plus"></i> Add Domain
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive border-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Domain Name</th>
                                <th>Default</th>
                                <th>Authentication (SPF/DKIM/DMARC)</th>
                                <th>Mapped Server</th>
                                <th>Rate Limit (/hr)</th>
                                <th>Status</th>
                                <th class="text-center pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="domains-table-body">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Loading sending domains...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Salesforce CRM Integration Settings -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <span class="fw-semibold text-zinc-900"><i class="bi bi-cloud me-1.5 text-zinc-500"></i> Salesforce CRM Integration Settings</span>
            </div>
            <div class="card-body p-4">
                <form id="crm-settings-form" onsubmit="saveCrmSettings(event)">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label mb-1">Connected App Client ID (Consumer Key)</label>
                            <input type="text" class="form-control" id="crm-client-id" placeholder="3MVG9qN..." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1">Connected App Client Secret (Consumer Secret)</label>
                            <input type="password" class="form-control" id="crm-client-secret" placeholder="••••••••••••••••" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">Salesforce Login URL</label>
                            <select class="form-select" id="crm-login-url">
                                <option value="https://login.salesforce.com">Production (login.salesforce.com)</option>
                                <option value="https://test.salesforce.com">Sandbox (test.salesforce.com)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">API Username</label>
                            <input type="email" class="form-control" id="crm-username" placeholder="api-user@b2bbulkmail.com" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">API Password + Token</label>
                            <input type="password" class="form-control" id="crm-token" placeholder="••••••••••••••••" required>
                        </div>
                        <div class="col-12 pt-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="crm-is-mock">
                                <label class="form-check-label text-zinc-700" for="crm-is-mock" style="font-size: 0.8125rem;">Enable Mock Mode (Use local CRM database fixtures instead of live Salesforce endpoints)</label>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-end pt-3 border-top mt-3">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-shield-check"></i> Save Salesforce Credentials</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Server Modal -->
<div class="modal fade" id="addServerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="add-server-form" onsubmit="createServer(event)">
                <div class="modal-header py-3 px-4" style="border-bottom: 1px solid var(--border-color);">
                    <h6 class="modal-title fw-semibold text-zinc-900">Configure Outbound SMTP Server</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.75rem;"></button>
                </div>
                <div class="modal-body py-3 px-4">
                    <div class="mb-2.5">
                        <label class="form-label mb-1">Server Name</label>
                        <input type="text" class="form-control" id="srv-name" placeholder="e.g. Primary Cluster" required>
                    </div>
                    <div class="row g-2 mb-2.5">
                        <div class="col-md-8">
                            <label class="form-label mb-1">SMTP Host</label>
                            <input type="text" class="form-control" id="srv-host" placeholder="smtp.mailserver.net" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">Port</label>
                            <input type="number" class="form-control" id="srv-port" placeholder="587" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-2.5">
                        <div class="col-md-6">
                            <label class="form-label mb-1">Username</label>
                            <input type="text" class="form-control" id="srv-username" placeholder="apikey">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1">Password</label>
                            <input type="password" class="form-control" id="srv-password" placeholder="secret">
                        </div>
                    </div>
                    <div class="mb-2.5">
                        <label class="form-label mb-1">Dedicated Outbound IP Address</label>
                        <input type="text" class="form-control" id="srv-ip" placeholder="e.g. 192.168.1.50" required>
                    </div>
                    <div class="form-check form-switch pt-1">
                        <input class="form-check-input" type="checkbox" id="srv-active" checked>
                        <label class="form-check-label text-zinc-700" style="font-size: 0.8125rem;">Server Active</label>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 p-3 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Server</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Domain Modal -->
<div class="modal fade" id="addDomainModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="add-domain-form" onsubmit="createDomain(event)">
                <div class="modal-header py-3 px-4" style="border-bottom: 1px solid var(--border-color);">
                    <h6 class="modal-title fw-semibold text-zinc-900">Configure Sending Domain</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.75rem;"></button>
                </div>
                <div class="modal-body py-3 px-4">
                    <div class="mb-2.5">
                        <label class="form-label mb-1">Domain Name</label>
                        <input type="text" class="form-control" id="dom-name" placeholder="e.g. outreach.company.com" required>
                    </div>
                    <div class="mb-2.5">
                        <label class="form-label mb-1">Mapped SMTP Server & IP</label>
                        <select class="form-select" id="dom-server" required>
                            <!-- Populated dynamically -->
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label mb-1">Hourly Limit</label>
                            <input type="number" class="form-control" id="dom-limit" value="1000" required>
                        </div>
                        <div class="col-md-6 align-self-end">
                            <div class="form-check form-switch pb-2">
                                <input class="form-check-input" type="checkbox" id="dom-default">
                                <label class="form-check-label text-zinc-700" style="font-size: 0.8125rem;">Default Domain</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-2.5 rounded-3 bg-zinc-50 border mb-2">
                        <span class="stat-label d-block mb-2">Authentication Status</span>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label mb-1" style="font-size: 0.68rem;">SPF</label>
                                <select class="form-select form-select-sm" id="dom-spf">
                                    <option value="verified" selected>Verified</option>
                                    <option value="unverified">Unverified</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label mb-1" style="font-size: 0.68rem;">DKIM</label>
                                <select class="form-select form-select-sm" id="dom-dkim">
                                    <option value="verified" selected>Verified</option>
                                    <option value="unverified">Unverified</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label mb-1" style="font-size: 0.68rem;">DMARC</label>
                                <select class="form-select form-select-sm" id="dom-dmarc">
                                    <option value="verified" selected>Verified</option>
                                    <option value="unverified">Unverified</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 p-3 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Domain</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('extra_scripts')
<script>
    let serversList = [];

    function loadInfrastructure() {
        // Load Servers
        fetch('/api/admin/servers')
            .then(res => res.json())
            .then(servers => {
                serversList = servers;
                const tbody = document.getElementById('servers-table-body');
                const select = document.getElementById('dom-server');
                
                if (servers.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No outbound SMTP servers configured.</td></tr>';
                    select.innerHTML = '<option value="">No Active Servers</option>';
                    return;
                }

                let tableHtml = '';
                let selectHtml = '';
                servers.forEach(s => {
                    const activeBadge = s.is_active === 1
                        ? '<span class="badge bg-success-soft">Active</span>'
                        : '<span class="badge bg-secondary-soft">Inactive</span>';
                    
                    tableHtml += `
                        <tr>
                            <td class="ps-4 fw-medium text-zinc-900">${escapeHtml(s.name)}</td>
                            <td><code class="text-zinc-600">${escapeHtml(s.host)}:${s.port}</code></td>
                            <td><span class="badge bg-secondary-soft font-monospace"><i class="bi bi-hdd-network me-1"></i>${escapeHtml(s.sending_ip)}</span></td>
                            <td>${activeBadge}</td>
                            <td class="text-center pe-4">
                                <button class="btn btn-outline-danger btn-xs" onclick="deleteServer(${s.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;

                    selectHtml += `<option value="${s.id}">${escapeHtml(s.name)} (${escapeHtml(s.sending_ip)})</option>`;
                });

                tbody.innerHTML = tableHtml;
                select.innerHTML = selectHtml;
            })
            .catch(err => console.error("Error loading servers:", err));

        // Load Domains
        fetch('/api/admin/domains')
            .then(res => res.json())
            .then(domains => {
                const tbody = document.getElementById('domains-table-body');
                if (domains.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No sending domains configured.</td></tr>';
                    return;
                }

                let html = '';
                domains.forEach(d => {
                    const spfB = d.spf_status === 'verified' ? 'success' : 'secondary';
                    const dkimB = d.dkim_status === 'verified' ? 'success' : 'secondary';
                    const dmarcB = d.dmarc_status === 'verified' ? 'success' : 'secondary';
                    const defaultBadge = d.is_default === 1 
                        ? '<span class="badge bg-primary-soft text-primary">Yes</span>' 
                        : '<span class="badge bg-secondary-soft">No</span>';

                    const statusBadge = d.status === 'enabled'
                        ? '<span class="badge bg-success-soft">Enabled</span>'
                        : '<span class="badge bg-secondary-soft">Disabled</span>';

                    html += `
                        <tr>
                            <td class="ps-4 fw-medium text-zinc-900">${escapeHtml(d.domain_name)}</td>
                            <td>${defaultBadge}</td>
                            <td>
                                <span class="badge bg-${spfB}-soft">SPF</span>
                                <span class="badge bg-${dkimB}-soft">DKIM</span>
                                <span class="badge bg-${dmarcB}-soft">DMARC</span>
                            </td>
                            <td><span class="text-zinc-600">${escapeHtml(d.server_name || 'Unassigned')}</span></td>
                            <td class="text-zinc-700">${d.rate_limit_per_hour.toLocaleString()}</td>
                            <td>${statusBadge}</td>
                            <td class="text-center pe-4">
                                <button class="btn btn-outline-danger btn-xs" onclick="deleteDomain(${d.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            })
            .catch(err => console.error("Error loading domains:", err));
    }

    function createServer(e) {
        e.preventDefault();
        const payload = {
            name: document.getElementById('srv-name').value,
            host: document.getElementById('srv-host').value,
            port: parseInt(document.getElementById('srv-port').value),
            username: document.getElementById('srv-username').value || null,
            password: document.getElementById('srv-password').value || null,
            sending_ip: document.getElementById('srv-ip').value,
            is_active: document.getElementById('srv-active').checked ? 1 : 0
        };

        fetch('/api/admin/servers', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                document.getElementById('add-server-form').reset();
                bootstrap.Modal.getInstance(document.getElementById('addServerModal')).hide();
                loadInfrastructure();
            } else {
                alert(data.error || "Failed to save server.");
            }
        })
        .catch(err => console.error("Error saving server:", err));
    }

    function deleteServer(id) {
        if (!confirm("Are you sure you want to delete this SMTP server configuration? Sending domains mapped to this will lose routing.")) {
            return;
        }

        fetch(`/api/admin/servers?id=${id}`, { method: 'DELETE' })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadInfrastructure();
                } else {
                    alert(data.error);
                }
            })
            .catch(err => console.error("Error deleting server:", err));
    }

    function createDomain(e) {
        e.preventDefault();
        const payload = {
            domain_name: document.getElementById('dom-name').value,
            server_id: parseInt(document.getElementById('dom-server').value),
            rate_limit_per_hour: parseInt(document.getElementById('dom-limit').value),
            is_default: document.getElementById('dom-default').checked ? 1 : 0,
            spf_status: document.getElementById('dom-spf').value,
            dkim_status: document.getElementById('dom-dkim').value,
            dmarc_status: document.getElementById('dom-dmarc').value,
            status: 'enabled'
        };

        fetch('/api/admin/domains', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                document.getElementById('add-domain-form').reset();
                bootstrap.Modal.getInstance(document.getElementById('addDomainModal')).hide();
                loadInfrastructure();
            } else {
                alert(data.error || "Failed to save domain.");
            }
        })
        .catch(err => console.error("Error saving domain:", err));
    }

    function deleteDomain(id) {
        if (!confirm("Are you sure you want to delete this sending domain?")) {
            return;
        }

        fetch(`/api/admin/domains?id=${id}`, { method: 'DELETE' })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadInfrastructure();
                } else {
                    alert(data.error);
                }
            })
            .catch(err => console.error("Error deleting domain:", err));
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function loadCrmSettings() {
        fetch('/api/admin/crm-settings')
            .then(res => res.json())
            .then(data => {
                document.getElementById('crm-client-id').value = data.salesforce_client_id || '';
                document.getElementById('crm-client-secret').value = data.salesforce_client_secret || '';
                document.getElementById('crm-login-url').value = data.salesforce_login_url || 'https://login.salesforce.com';
                document.getElementById('crm-username').value = data.salesforce_username || '';
                document.getElementById('crm-token').value = data.salesforce_token_or_password || '';
                document.getElementById('crm-is-mock').checked = data.is_mock === 1;
            })
            .catch(err => console.error("Error loading CRM settings:", err));
    }

    function saveCrmSettings(e) {
        e.preventDefault();
        const payload = {
            salesforce_client_id: document.getElementById('crm-client-id').value,
            salesforce_client_secret: document.getElementById('crm-client-secret').value,
            salesforce_login_url: document.getElementById('crm-login-url').value,
            salesforce_username: document.getElementById('crm-username').value,
            salesforce_token_or_password: document.getElementById('crm-token').value,
            is_mock: document.getElementById('crm-is-mock').checked ? 1 : 0
        };

        fetch('/api/admin/crm-settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                loadCrmSettings();
            } else {
                alert(data.error || "Failed to update CRM settings.");
            }
        })
        .catch(err => console.error("Error saving CRM settings:", err));
    }

    window.onload = function() {
        loadInfrastructure();
        loadCrmSettings();
    };
</script>
@endsection
