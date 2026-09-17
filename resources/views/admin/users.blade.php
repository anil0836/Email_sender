@extends('layouts.app')

@section('page_title', 'User Management & Limits')

@section('content')
<div class="row g-4">
    <!-- User Creation Form (Top) -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold text-zinc-900"><i class="bi bi-person-plus me-1.5 text-zinc-500"></i> Register New User</h6>
            </div>
            <div class="card-body">
                <form id="create-user-form" onsubmit="createUser(event)">
                    <div class="row g-3">
                        <div class="col-md-4 col-sm-6">
                            <label for="new-empid" class="form-label mb-1">Employee ID</label>
                            <input type="text" class="form-control" id="new-empid" placeholder="e.g. EMP-101" required>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label for="new-username" class="form-label mb-1">Display Name</label>
                            <input type="text" class="form-control" id="new-username" placeholder="e.g. Clark Kent" required>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label for="new-email" class="form-label mb-1">Email (Login Identity)</label>
                            <input type="email" class="form-control" id="new-email" placeholder="e.g. clark@b2bbulkmail.com" required>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label for="new-password" class="form-label mb-1">Password</label>
                            <input type="password" class="form-control" id="new-password" placeholder="••••••••" required>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label for="new-role" class="form-label mb-1">Application Role</label>
                            <select class="form-select" id="new-role" onchange="toggleManagerDropdown('new')">
                                <option value="user" selected>User (Outbound Sender)</option>
                                <option value="manager">Manager (Approver)</option>
                                <option value="admin">Admin (Full Access)</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6" id="manager-select-container">
                            <label for="new-manager" class="form-label mb-1">Assigned Manager</label>
                            <select class="form-select" id="new-manager">
                                <option value="">-- Select Manager --</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label for="new-server" class="form-label mb-1">Assigned Server & IP</label>
                            <select class="form-select" id="new-server">
                                <option value="">-- Default Server --</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label for="new-domain" class="form-label mb-1">Approved Domain</label>
                            <select class="form-select" id="new-domain">
                                <option value="">-- Default Domain --</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label for="new-limit" class="form-label mb-1">Daily Limit (Emails/Day)</label>
                            <input type="number" class="form-control" id="new-limit" value="1000" min="1" required>
                        </div>
                        <div class="col-12 text-end pt-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus"></i> Create Account
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Users List (Middle) -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold text-zinc-900"><i class="bi bi-people me-1.5 text-zinc-500"></i> Registered Application Accounts</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive border-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">User</th>
                                <th>Emp ID</th>
                                <th>Role</th>
                                <th>Manager</th>
                                <th>Status</th>
                                <th class="text-center" style="width: 220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm text-zinc-400 me-2" role="status"></div>
                                    Loading accounts...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Logs Section -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold text-zinc-900"><i class="bi bi-shield-check me-1.5 text-zinc-500"></i> Security & Activity Audit Log</h6>
                <button class="btn btn-outline-secondary btn-xs" onclick="loadAuditLogs()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive border-0" style="max-height: 380px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="sticky-top">
                            <tr>
                                <th class="ps-4">Timestamp</th>
                                <th>Actor</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody id="audit-logs-table-body">
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-zinc-400 me-2" role="status"></div>
                                    Loading audit logs...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-3 px-4" style="border-bottom: 1px solid var(--border-color);">
                <h6 class="modal-title fw-semibold text-zinc-900"><i class="bi bi-pencil-square me-1.5"></i> Edit Account</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.75rem;"></button>
            </div>
            <div class="modal-body py-3 px-4">
                <form id="edit-user-form" onsubmit="submitEditUser(event)">
                    <input type="hidden" id="edit-userid">
                    
                    <div class="mb-2.5">
                        <label for="edit-empid" class="form-label mb-1">Employee ID</label>
                        <input type="text" class="form-control" id="edit-empid" required>
                    </div>
                    <div class="mb-2.5">
                        <label for="edit-username" class="form-label mb-1">Display Name</label>
                        <input type="text" class="form-control" id="edit-username" required>
                    </div>
                    <div class="mb-2.5">
                        <label for="edit-email" class="form-label mb-1">Email Address</label>
                        <input type="email" class="form-control" id="edit-email" required>
                    </div>
                    <div class="mb-2.5">
                        <label for="edit-password" class="form-label mb-1">Password (Leave blank to keep current)</label>
                        <input type="password" class="form-control" id="edit-password" placeholder="••••••••">
                    </div>
                    <div class="mb-2.5">
                        <label for="edit-role" class="form-label mb-1">Role</label>
                        <select class="form-select" id="edit-role" onchange="toggleManagerDropdown('edit')">
                            <option value="user">User (Outbound Sender)</option>
                            <option value="manager">Manager (Approver)</option>
                            <option value="admin">Admin (Full Access)</option>
                        </select>
                    </div>
                    <div class="mb-2.5" id="edit-manager-select-container">
                        <label for="edit-manager" class="form-label mb-1">Assigned Manager</label>
                        <select class="form-select" id="edit-manager">
                            <option value="">-- Select Manager --</option>
                        </select>
                    </div>
                    <div class="mb-2.5">
                        <label for="edit-server" class="form-label mb-1">Sending Server & IP</label>
                        <select class="form-select" id="edit-server">
                            <option value="">-- Use Default Sending Server --</option>
                        </select>
                    </div>
                    <div class="mb-2.5">
                        <label for="edit-domain" class="form-label mb-1">Approved Domain</label>
                        <select class="form-select" id="edit-domain">
                            <option value="">-- Use Default Sending Domain --</option>
                        </select>
                    </div>
                    <div class="mb-2.5">
                        <label for="edit-limit" class="form-label mb-1">Daily Sending Limit</label>
                        <input type="number" class="form-control" id="edit-limit" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-blocked" class="form-label mb-1">Status</label>
                        <select class="form-select" id="edit-blocked">
                            <option value="0">Active (Access Granted)</option>
                            <option value="1">Blocked (Suspended / Disabled)</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('extra_scripts')
<script>
    let editModalInstance = null;
    let allUsers = [];
    const currentUserId = {{ session('user_id', Auth::id() ?? 0) }};

    function loadUsers() {
        fetch('/api/admin/users')
            .then(res => res.json())
            .then(users => {
                allUsers = users;
                const tbody = document.getElementById('users-table-body');
                if (users.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No users registered.</td></tr>';
                    return;
                }

                let html = '';
                users.forEach(u => {
                    const isCurrentUser = u.id == currentUserId;
                    
                    let roleBadge = '';
                    if (u.role === 'admin') {
                        roleBadge = '<span class="badge bg-danger-soft">Admin</span>';
                    } else if (u.role === 'manager') {
                        roleBadge = '<span class="badge bg-success-soft">Manager</span>';
                    } else {
                        roleBadge = '<span class="badge bg-secondary-soft">User</span>';
                    }

                    const statusBadge = u.is_blocked == 1
                        ? '<span class="badge bg-danger-soft"><i class="bi bi-slash-circle me-1"></i>Blocked</span>'
                        : '<span class="badge bg-success-soft"><i class="bi bi-check-circle me-1"></i>Active</span>';

                    const init = (u.username || '?').substring(0, 1).toUpperCase();

                    html += `
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-zinc-100 text-zinc-800 border" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                        ${init}
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-zinc-900">${escapeHtml(u.username)} ${isCurrentUser ? '<span class="text-zinc-400 fs-8 fw-normal">(You)</span>' : ''}</div>
                                        <div class="text-zinc-500" style="font-size: 0.74rem;">${escapeHtml(u.email || 'No email')} &bull; Limit: <strong>${u.daily_limit || 1000}</strong>/day</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="font-monospace text-zinc-700">${escapeHtml(u.emp_id || 'N/A')}</span></td>
                            <td>${roleBadge}</td>
                            <td>
                                ${u.manager_username ? `
                                    <div class="fw-medium text-zinc-800">${escapeHtml(u.manager_name || u.manager_username)}</div>
                                    ${u.manager_email ? `<div class="text-zinc-500 font-monospace" style="font-size: 0.73rem;">${escapeHtml(u.manager_email)}</div>` : ''}
                                ` : '<span class="text-zinc-400">None</span>'}
                            </td>
                            <td>${statusBadge}</td>
                            <td>
                                <div class="d-flex gap-1 justify-content-center">
                                    <button class="btn btn-outline-secondary btn-xs" onclick="openEditModal(${u.id})">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="btn btn-xs ${u.is_blocked == 1 ? 'btn-outline-secondary' : 'btn-outline-secondary'}" 
                                            onclick="toggleUserBlock(${u.id}, '${escapeHtml(u.username)}', ${u.is_blocked})"
                                            ${isCurrentUser ? 'disabled' : ''}>
                                        <i class="bi ${u.is_blocked == 1 ? 'bi-check' : 'bi-slash-circle'}"></i> ${u.is_blocked == 1 ? 'Unblock' : 'Block'}
                                    </button>
                                    <button class="btn btn-outline-danger btn-xs" 
                                            onclick="deleteUser(${u.id}, '${escapeHtml(u.username)}')"
                                            ${isCurrentUser ? 'disabled' : ''}>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            })
            .catch(err => console.error("Error loading users:", err));
    }

    function toggleManagerDropdown(context) {
        const role = document.getElementById(`${context}-role`).value;
        const container = document.getElementById(`${context}-manager-select-container`);
        const select = document.getElementById(`${context}-manager`);
        if (role === 'user') {
            container.style.display = 'block';
            select.required = true;
        } else {
            container.style.display = 'none';
            select.required = false;
            select.value = '';
        }
    }

    function loadManagers() {
        fetch('/api/admin/managers')
            .then(res => res.json())
            .then(managers => {
                const selectNew = document.getElementById('new-manager');
                selectNew.innerHTML = '<option value="">-- Select Manager --</option>';
                managers.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    const displayName = m.name || m.username;
                    const emailPart = m.email ? ` <${m.email}>` : '';
                    opt.textContent = `${displayName}${emailPart} (${m.role})`;
                    selectNew.appendChild(opt);
                });

                const selectEdit = document.getElementById('edit-manager');
                selectEdit.innerHTML = '<option value="">-- Select Manager --</option>';
                managers.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    const displayName = m.name || m.username;
                    const emailPart = m.email ? ` <${m.email}>` : '';
                    opt.textContent = `${displayName}${emailPart} (${m.role})`;
                    selectEdit.appendChild(opt);
                });
            })
            .catch(err => console.error("Error loading managers:", err));
    }

    function createUser(e) {
        e.preventDefault();
        const emp_id = document.getElementById('new-empid').value;
        const username = document.getElementById('new-username').value;
        const email = document.getElementById('new-email').value;
        const password = document.getElementById('new-password').value;
        const role = document.getElementById('new-role').value;
        const manager_id = document.getElementById('new-manager').value;
        const assigned_server_id = document.getElementById('new-server').value;
        const assigned_domain_id = document.getElementById('new-domain').value;
        const daily_limit = document.getElementById('new-limit').value;

        fetch('/api/admin/users', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ emp_id, username, email, password, role, manager_id, assigned_server_id, assigned_domain_id, daily_limit })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                document.getElementById('create-user-form').reset();
                loadUsers();
                loadManagers();
                toggleManagerDropdown('new');
            } else {
                alert(data.error || "Failed to create user.");
            }
        })
        .catch(err => console.error("Error creating user:", err));
    }

    function openEditModal(userId) {
        const u = allUsers.find(user => user.id == userId);
        if (!u) return;

        document.getElementById('edit-userid').value = u.id;
        document.getElementById('edit-empid').value = u.emp_id || '';
        document.getElementById('edit-username').value = u.username || '';
        document.getElementById('edit-email').value = u.email || '';
        document.getElementById('edit-password').value = '';
        document.getElementById('edit-role').value = u.role || 'user';
        document.getElementById('edit-manager').value = u.manager_id || '';
        document.getElementById('edit-server').value = u.assigned_server_id || '';
        document.getElementById('edit-domain').value = u.assigned_domain_id || '';
        document.getElementById('edit-limit').value = u.daily_limit || '1000';
        document.getElementById('edit-blocked').value = u.is_blocked || '0';

        toggleManagerDropdown('edit');

        if (!editModalInstance) {
            editModalInstance = new bootstrap.Modal(document.getElementById('editUserModal'));
        }
        editModalInstance.show();
    }

    function submitEditUser(e) {
        e.preventDefault();
        
        const id = document.getElementById('edit-userid').value;
        const emp_id = document.getElementById('edit-empid').value;
        const username = document.getElementById('edit-username').value;
        const email = document.getElementById('edit-email').value;
        const password = document.getElementById('edit-password').value;
        const role = document.getElementById('edit-role').value;
        const manager_id = document.getElementById('edit-manager').value;
        const assigned_server_id = document.getElementById('edit-server').value;
        const assigned_domain_id = document.getElementById('edit-domain').value;
        const daily_limit = document.getElementById('edit-limit').value;
        const is_blocked = document.getElementById('edit-blocked').value;

        fetch('/api/admin/users', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, emp_id, username, email, password, role, manager_id, assigned_server_id, assigned_domain_id, daily_limit, is_blocked })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                editModalInstance.hide();
                loadUsers();
                loadManagers();
            } else {
                alert(data.error || "Failed to update user.");
            }
        })
        .catch(err => console.error("Error editing user:", err));
    }

    function toggleUserBlock(userId, username, currentBlockState) {
        const action = currentBlockState == 1 ? "unblock" : "block";
        if (!confirm(`Are you sure you want to ${action} user "${username}"?`)) {
            return;
        }

        const u = allUsers.find(user => user.id == userId);
        if (!u) return;

        const newBlockState = currentBlockState == 1 ? 0 : 1;

        fetch('/api/admin/users', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: userId,
                emp_id: u.emp_id,
                username: u.username,
                email: u.email,
                role: u.role,
                manager_id: u.manager_id,
                assigned_server_id: u.assigned_server_id,
                assigned_domain_id: u.assigned_domain_id,
                is_blocked: newBlockState
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                loadUsers();
            } else {
                alert(data.error || "Failed to change block state.");
            }
        })
        .catch(err => console.error("Error toggling user block:", err));
    }

    function deleteUser(id, username) {
        if (!confirm(`Are you sure you want to permanently delete user "${username}"?`)) {
            return;
        }

        fetch(`/api/admin/users?id=${id}`, { method: 'DELETE' })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadUsers();
                    loadManagers();
                } else {
                    alert(data.error || "Failed to delete user.");
                }
            })
            .catch(err => console.error("Error deleting user:", err));
    }

    function loadServers() {
        fetch('/api/admin/servers')
            .then(res => res.json())
            .then(servers => {
                const selectNew = document.getElementById('new-server');
                selectNew.innerHTML = '<option value="">-- Use Default Sending Server --</option>';
                servers.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = `${s.name} (${s.sending_ip})`;
                    selectNew.appendChild(opt);
                });

                const selectEdit = document.getElementById('edit-server');
                selectEdit.innerHTML = '<option value="">-- Use Default Sending Server --</option>';
                servers.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = `${s.name} (${s.sending_ip})`;
                    selectEdit.appendChild(opt);
                });
            })
            .catch(err => console.error("Error loading servers:", err));
    }

    function loadDomains() {
        fetch('/api/admin/domains')
            .then(res => res.json())
            .then(domains => {
                const selectNew = document.getElementById('new-domain');
                selectNew.innerHTML = '<option value="">-- Use Default Sending Domain --</option>';
                domains.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.domain_name;
                    selectNew.appendChild(opt);
                });

                const selectEdit = document.getElementById('edit-domain');
                selectEdit.innerHTML = '<option value="">-- Use Default Sending Domain --</option>';
                domains.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.domain_name;
                    selectEdit.appendChild(opt);
                });
            })
            .catch(err => console.error("Error loading domains:", err));
    }

    function loadAuditLogs() {
        fetch('/api/admin/audit-logs')
            .then(res => res.json())
            .then(logs => {
                const tbody = document.getElementById('audit-logs-table-body');
                if (logs.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No audit logs recorded yet.</td></tr>';
                    return;
                }
                
                let html = '';
                logs.forEach(log => {
                    const actor = log.username ? `${escapeHtml(log.username)} (ID: ${log.user_id})` : 'System / Anonymous';
                    html += `
                        <tr>
                            <td class="ps-4 text-zinc-500">${escapeHtml(log.created_at)}</td>
                            <td class="fw-medium text-zinc-900">${actor}</td>
                            <td><span class="badge bg-secondary-soft font-monospace">${escapeHtml(log.action)}</span></td>
                            <td class="text-zinc-600">${escapeHtml(log.details || 'No details')}</td>
                            <td class="text-zinc-500 font-monospace" style="font-size: 0.76rem;">${escapeHtml(log.ip_address || 'N/A')}</td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            })
            .catch(err => console.error("Error loading audit logs:", err));
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
        loadUsers();
        loadManagers();
        loadServers();
        loadDomains();
        loadAuditLogs();
        toggleManagerDropdown('new');
    };
</script>
@endsection
