@extends('layouts.app')

@section('page_title', 'Developer & Testing Simulator')

@section('content')
<div class="row g-4">
    <!-- Salesforce Sim: Leads & Contacts Manager -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-zinc-900"><i class="bi bi-cloud me-1.5 text-zinc-500"></i> Salesforce Simulated CRM Directory</span>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSfRecordModal">
                    <i class="bi bi-person-plus"></i> Add Record
                </button>
            </div>
            <div class="card-body p-0">
                <div class="p-3 bg-zinc-50 border-bottom text-zinc-600" style="font-size: 0.775rem;">
                    <i class="bi bi-info-circle me-1 text-zinc-500"></i> Real-time edits simulate <strong>Salesforce Change Data Capture (CDC)</strong> events and instantly invalidate server-side recipient cache entries.
                </div>
                <div class="table-responsive border-0" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Record ID</th>
                                <th>Contact / Lead</th>
                                <th>Email Address</th>
                                <th>CRM Owner</th>
                                <th>Opt Out</th>
                                <th>Status</th>
                                <th>Consent</th>
                                <th class="text-center pe-4">Cache Action</th>
                            </tr>
                        </thead>
                        <tbody id="sf-records-table-body">
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm text-zinc-400 me-2" role="status"></div>
                                    Loading CRM records...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Provider Webhooks Trigger -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold text-zinc-900"><i class="bi bi-activity me-1.5 text-zinc-500"></i> Outbound Delivery & Webhook Triggers</h6>
            </div>
            <div class="card-body p-0">
                <div class="p-3 bg-zinc-50 border-bottom text-zinc-600" style="font-size: 0.775rem;">
                    Simulate SMTP provider delivery receipts, open tracking, clicks, bounce codes, and spam complaints.
                </div>
                <div class="table-responsive border-0" style="max-height: 380px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Recipient</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th class="text-center pe-4">Simulate Event</th>
                            </tr>
                        </thead>
                        <tbody id="outbound-logs-table-body">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No sent logs available. Send a campaign first!</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Inbound Email Reply Simulator -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold text-zinc-900"><i class="bi bi-reply me-1.5 text-zinc-500"></i> Simulate Inbound Reply</h6>
            </div>
            <div class="card-body p-4">
                <form id="sim-reply-form" onsubmit="triggerInboundReply(event)">
                    <div class="mb-2.5">
                        <label class="form-label mb-1">Target Campaign</label>
                        <select class="form-select form-select-sm" id="reply-campaign-id" onchange="loadCampaignRecipients()" required>
                            <option value="">Choose Campaign...</option>
                        </select>
                    </div>
                    <div class="mb-2.5">
                        <label class="form-label mb-1">Recipient Sender</label>
                        <select class="form-select form-select-sm" id="reply-recipient-email" required>
                            <option value="">Choose Recipient...</option>
                        </select>
                    </div>
                    <div class="mb-2.5">
                        <label class="form-label mb-1">Reply Subject</label>
                        <input type="text" class="form-control form-control-sm" id="reply-subject" value="Re: Project details" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1">Reply Body</label>
                        <textarea class="form-control form-control-sm" id="reply-body" rows="3" required>Hey, I received your email. I'd love to schedule a follow-up demo. Let me know when you're free.</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-sm">
                        <i class="bi bi-send"></i> Dispatch Mock Inbound Reply
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Salesforce CRM Record Modal -->
<div class="modal fade" id="addSfRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="add-sf-record-form" onsubmit="createSfRecord(event)">
                <div class="modal-header py-3 px-4" style="border-bottom: 1px solid var(--border-color);">
                    <h6 class="modal-title fw-semibold text-zinc-900">Add Simulated CRM Record</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.75rem;"></button>
                </div>
                <div class="modal-body py-3 px-4">
                    <div class="row g-2 mb-2.5">
                        <div class="col-md-6">
                            <label class="form-label mb-1">First Name</label>
                            <input type="text" class="form-control" id="sf-first-name" placeholder="John" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1">Last Name</label>
                            <input type="text" class="form-control" id="sf-last-name" placeholder="Doe" required>
                        </div>
                    </div>
                    <div class="mb-2.5">
                        <label class="form-label mb-1">Email Address</label>
                        <input type="email" class="form-control" id="sf-email" placeholder="john.doe@company.com" required>
                    </div>
                    <div class="row g-2 mb-2.5">
                        <div class="col-md-6">
                            <label class="form-label mb-1">Object Type</label>
                            <select class="form-select" id="sf-object-type">
                                <option value="Contact" selected>Contact</option>
                                <option value="Lead">Lead</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1">Salesforce Owner</label>
                            <select class="form-select" id="sf-owner-id">
                                <option value="admin">admin</option>
                                <option value="user" selected>user</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-2.5">
                        <div class="col-md-6">
                            <label class="form-label mb-1">Status</label>
                            <select class="form-select" id="sf-status">
                                <option value="Active" selected>Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Disqualified">Disqualified</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1">GDPR Consent</label>
                            <select class="form-select" id="sf-consent">
                                <option value="valid" selected>Valid</option>
                                <option value="missing">Missing</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-check form-switch pt-1">
                        <input class="form-check-input" type="checkbox" id="sf-opted-out">
                        <label class="form-check-label text-zinc-700" style="font-size: 0.8125rem;">HasOptedOutOfEmail</label>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 p-3 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save to CRM</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('extra_scripts')
<script>
    let activeCampaigns = [];
    let campaignRecipientMap = {};

    function loadSalesforceRecords() {
        fetch('/api/simulator/salesforce-records')
            .then(res => res.json())
            .then(records => {
                const tbody = document.getElementById('sf-records-table-body');
                if (records.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No Salesforce records found.</td></tr>';
                    return;
                }

                let html = '';
                records.forEach(r => {
                    const typeBadge = r.object_type === 'Contact' 
                        ? '<span class="badge bg-secondary-soft">Contact</span>' 
                        : '<span class="badge bg-warning-soft">Lead</span>';
                    
                    const optOutChecked = r.opted_out === 1 ? 'checked' : '';
                    const consentClass = r.consent_status === 'valid' ? 'badge bg-success-soft' : 'badge bg-danger-soft';

                    html += `
                        <tr id="sf-row-${r.id}">
                            <td class="ps-4"><code class="text-zinc-600">${r.id}</code></td>
                            <td><div class="fw-semibold text-zinc-900">${escapeHtml(r.first_name)} ${escapeHtml(r.last_name)}</div>${typeBadge}</td>
                            <td class="text-zinc-700">${escapeHtml(r.email)}</td>
                            <td>
                                <select class="form-select form-select-sm py-1" style="font-size: 0.76rem;" onchange="updateSfField('${r.id}', 'owner_id', this.value)">
                                    <option value="admin" ${r.owner_id === 'admin' ? 'selected' : ''}>admin</option>
                                    <option value="user" ${r.owner_id === 'user' ? 'selected' : ''}>user</option>
                                </select>
                            </td>
                            <td>
                                <div class="form-check form-switch py-0 mb-0">
                                    <input class="form-check-input" type="checkbox" onchange="updateSfField('${r.id}', 'opted_out', this.checked ? 1 : 0)" ${optOutChecked}>
                                </div>
                            </td>
                            <td>
                                <select class="form-select form-select-sm py-1" style="font-size: 0.76rem;" onchange="updateSfField('${r.id}', 'status', this.value)">
                                    <option value="Active" ${r.status === 'Active' ? 'selected' : ''}>Active</option>
                                    <option value="Inactive" ${r.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                                    <option value="Disqualified" ${r.status === 'Disqualified' ? 'selected' : ''}>Disqualified</option>
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm py-1" style="font-size: 0.76rem;" onchange="updateSfField('${r.id}', 'consent_status', this.value)">
                                    <option value="valid" ${r.consent_status === 'valid' ? 'selected' : ''}>Valid</option>
                                    <option value="missing" ${r.consent_status === 'missing' ? 'selected' : ''}>Missing</option>
                                </select>
                            </td>
                            <td class="text-center pe-4">
                                <button class="btn btn-outline-secondary btn-xs" onclick="invalidateCache('${r.id}')" title="Clear Cache">
                                    <i class="bi bi-arrow-repeat"></i> Flush
                                </button>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            });
    }

    function createSfRecord(e) {
        e.preventDefault();
        const payload = {
            first_name: document.getElementById('sf-first-name').value,
            last_name: document.getElementById('sf-last-name').value,
            email: document.getElementById('sf-email').value,
            object_type: document.getElementById('sf-object-type').value,
            owner_id: document.getElementById('sf-owner-id').value,
            status: document.getElementById('sf-status').value,
            consent_status: document.getElementById('sf-consent').value,
            opted_out: document.getElementById('sf-opted-out').checked ? 1 : 0
        };

        fetch('/api/simulator/salesforce-records', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Simulated Salesforce Record saved!");
                document.getElementById('add-sf-record-form').reset();
                bootstrap.Modal.getInstance(document.getElementById('addSfRecordModal')).hide();
                loadSalesforceRecords();
            }
        });
    }

    function updateSfField(recordId, fieldName, value) {
        const updates = {};
        updates[fieldName] = value;

        fetch('/api/simulator/update-salesforce', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: recordId, updates: updates })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                console.log(`Salesforce record ${recordId} field ${fieldName} updated to ${value}`);
            }
        });
    }

    function invalidateCache(recordId) {
        fetch('/api/simulator/update-salesforce', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: recordId, updates: {} })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Cache cleared for record: " + recordId);
            }
        });
    }

    function loadOutboundLogs() {
        fetch('/api/dashboard/stats')
            .then(res => res.json())
            .then(data => {
                const campaigns = data.recent_campaigns;
                activeCampaigns = campaigns;
                
                const selectC = document.getElementById('reply-campaign-id');
                let selectHtml = '<option value="">Choose Campaign...</option>';
                campaigns.forEach(c => {
                    selectHtml += `<option value="${c.id}">${escapeHtml(c.subject)}</option>`;
                });
                selectC.innerHTML = selectHtml;

                const tbody = document.getElementById('outbound-logs-table-body');
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Loading sent logs...</td></tr>';

                const promises = campaigns.slice(0, 3).map(c => fetch(`/api/campaign/${c.id}`).then(res => res.json()));
                
                Promise.all(promises).then(detailsList => {
                    let html = '';
                    let count = 0;
                    campaignRecipientMap = {};

                    detailsList.forEach(data => {
                        const c = data.campaign;
                        const logs = data.recipient_logs.filter(l => l.decision === 'approved');
                        campaignRecipientMap[c.id] = logs.map(l => l.email);

                        logs.forEach(l => {
                            count++;
                            const statusBadges = {
                                "queued": '<span class="badge bg-warning-soft">Queued</span>',
                                "sent": '<span class="badge bg-secondary-soft">Sent</span>',
                                "delivered": '<span class="badge bg-success-soft">Delivered</span>',
                                "opened": '<span class="badge bg-secondary-soft text-zinc-900">Opened</span>',
                                "bounce": '<span class="badge bg-danger-soft">Bounced</span>',
                                "spam_complaint": '<span class="badge bg-danger-soft">Spam</span>',
                                "unsubscribed": '<span class="badge bg-secondary-soft">Unsubscribed</span>'
                            };

                            let webhookActions = '';
                            if (l.delivery_status === 'sent') {
                                webhookActions += `
                                    <button class="btn btn-xs btn-outline-secondary text-emerald-700" onclick="triggerWebhook('${l.provider_message_id}', 'delivered')">Deliver</button>
                                    <button class="btn btn-xs btn-outline-danger" onclick="triggerWebhook('${l.provider_message_id}', 'bounce', '550 User Unknown')">Bounce</button>
                                `;
                            }
                            if (l.delivery_status in { 'sent': 1, 'delivered': 1 }) {
                                webhookActions += `
                                    <button class="btn btn-xs btn-outline-secondary" onclick="simulateOpen('${l.tracking_token}')">Open</button>
                                    <button class="btn btn-xs btn-outline-danger" onclick="triggerWebhook('${l.provider_message_id}', 'spam_complaint')">Spam</button>
                                `;
                            }
                            if (l.delivery_status in { 'sent': 1, 'delivered': 1, 'opened': 1 }) {
                                webhookActions += `
                                    <button class="btn btn-xs btn-outline-secondary" onclick="simulateClick('${l.tracking_token}')"><i class="bi bi-cursor"></i> Click</button>
                                    <a class="btn btn-xs btn-outline-secondary" href="/track/unsubscribe/${l.tracking_token}" target="_blank">Opt-out</a>
                                `;
                            }

                            html += `
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-semibold text-zinc-900">${escapeHtml(l.email)}</div>
                                        <div class="text-zinc-400 font-monospace" style="font-size: 0.72rem;">Msg ID: ${l.provider_message_id || 'N/A'}</div>
                                    </td>
                                    <td>
                                        <div class="text-truncate text-zinc-700" style="max-width: 140px; font-size: 0.78rem;">${escapeHtml(c.subject)}</div>
                                    </td>
                                    <td>${statusBadges[l.delivery_status] || l.delivery_status}</td>
                                    <td class="text-center pe-4">
                                        <div class="d-flex justify-content-center gap-1">
                                            ${webhookActions || '<span class="text-zinc-400">-</span>'}
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                    });

                    if (count === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No deliverable messages available. Send a campaign first!</td></tr>';
                    } else {
                        tbody.innerHTML = html;
                    }
                });
            });
    }

    function loadCampaignRecipients() {
        const campaignId = document.getElementById('reply-campaign-id').value;
        const selectR = document.getElementById('reply-recipient-email');
        
        if (!campaignId || !campaignRecipientMap[campaignId]) {
            selectR.innerHTML = '<option value="">Choose Recipient...</option>';
            return;
        }

        let html = '<option value="">Choose Recipient...</option>';
        campaignRecipientMap[campaignId].forEach(email => {
            html += `<option value="${escapeHtml(email)}">${escapeHtml(email)}</option>`;
        });
        selectR.innerHTML = html;
    }

    function triggerWebhook(messageId, eventType, errMsg = null) {
        fetch('/api/simulator/trigger-webhook', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                provider_message_id: messageId,
                event: eventType,
                error_message: errMsg
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadOutboundLogs();
            } else {
                alert(data.error);
            }
        });
    }

    function simulateOpen(token) {
        const geos = [
            { c: "US", r: "California", ci: "San Francisco" },
            { c: "IN", r: "Maharashtra", ci: "Mumbai" },
            { c: "GB", r: "England", ci: "London" },
            { c: "CA", r: "Ontario", ci: "Toronto" },
            { c: "JP", r: "Tokyo", ci: "Chiyoda" }
        ];
        const randomGeo = geos[Math.floor(Math.random() * geos.length)];
        
        const country = prompt("Enter Country Code (2 letters) to map:", randomGeo.c);
        if (country === null) return;
        const region = prompt("Enter Region/State Name:", randomGeo.r);
        const city = prompt("Enter City Name:", randomGeo.ci);

        let url = `/track/open/${token}?country=${country}&region=${region}&city=${city}`;
        
        fetch(url)
            .then(res => {
                alert("Simulated open event registered in: " + city + ", " + country);
                loadOutboundLogs();
            });
    }

    function simulateClick(token) {
        window.open(`/track/click/${token}?url=https://www.google.com`, '_blank');
        setTimeout(loadOutboundLogs, 1000);
    }

    function triggerInboundReply(e) {
        e.preventDefault();
        const campaign_id = document.getElementById('reply-campaign-id').value;
        const email = document.getElementById('reply-recipient-email').value;
        const subject = document.getElementById('reply-subject').value;
        const body = document.getElementById('reply-body').value;

        fetch('/api/simulator/inbound-reply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ campaign_id, email, subject, body })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Inbound reply recorded!");
                document.getElementById('sim-reply-form').reset();
            } else {
                alert(data.error);
            }
        });
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

    window.onload = function() {
        loadSalesforceRecords();
        loadOutboundLogs();
    };
</script>
@endsection
