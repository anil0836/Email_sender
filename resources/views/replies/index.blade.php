@extends('layouts.app')

@section('page_title', 'Inbound Replies & Conversations')

@section('content')
<div class="row g-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-zinc-900"><i class="bi bi-chat-left-text me-1.5 text-zinc-500"></i> Inbound Reply Feed & Salesforce Thread Linking</span>
                <span class="badge bg-secondary-soft" id="reply-count">0 Threads</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive border-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Sender / Recipient</th>
                                <th>Campaign Subject</th>
                                <th>Inbound Response</th>
                                <th>Salesforce Link</th>
                                <th class="pe-4">Received</th>
                            </tr>
                        </thead>
                        <tbody id="replies-table-body">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm text-zinc-400 me-2" role="status"></div>
                                    Loading replies...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('extra_scripts')
<script>
    function loadReplies() {
        fetch('/api/replies')
            .then(res => res.json())
            .then(replies => {
                document.getElementById('reply-count').innerText = `${replies.length} Threads`;
                const tbody = document.getElementById('replies-table-body');
                
                if (replies.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No inbound replies received yet.</td></tr>';
                    return;
                }

                let html = '';
                replies.forEach(r => {
                    html += `
                        <tr>
                            <td class="ps-4">
                                <div class="fw-semibold text-zinc-900">${escapeHtml(r.recipient_email)}</div>
                            </td>
                            <td>
                                <div class="text-zinc-800 fw-medium">${escapeHtml(r.original_subject)}</div>
                                <div class="text-zinc-400 font-monospace" style="font-size: 0.72rem;">Ref: ${r.campaign_id.substring(0,8)}...</div>
                            </td>
                            <td>
                                <div class="fw-semibold text-zinc-900" style="font-size: 0.8125rem;">${escapeHtml(r.reply_subject)}</div>
                                <div class="text-zinc-500 text-truncate" style="max-width: 280px; font-size: 0.775rem;" title="${escapeHtml(r.reply_body)}">${escapeHtml(r.reply_body)}</div>
                            </td>
                            <td>
                                <div style="font-size: 0.775rem;">
                                    <span class="d-block text-zinc-700"><i class="bi bi-link-45deg text-zinc-400 me-1"></i>Record: <code class="text-zinc-800">${escapeHtml(r.mapped_salesforce_record_id || 'N/A')}</code></span>
                                    <span class="d-block text-zinc-500"><i class="bi bi-person me-1 text-zinc-400"></i>Owner: <strong>${escapeHtml(r.mapped_owner_id || 'unassigned')}</strong></span>
                                </div>
                            </td>
                            <td class="text-zinc-500 pe-4" style="font-size: 0.76rem;">${new Date(r.received_at).toLocaleString()}</td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            })
            .catch(err => console.error("Error loading replies:", err));
    }

    function escapeHtml(text) {
        return text
            ? text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;")
            : '';
    }

    window.onload = loadReplies;
</script>
@endsection
