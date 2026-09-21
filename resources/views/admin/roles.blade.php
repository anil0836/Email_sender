@extends('layouts.app')

@section('page_title', 'Role & Permission Management')

@section('content')
<div class="row g-4">
    <!-- Header Banner -->
    <div class="col-12">
        <div class="card bg-white shadow-sm border-0">
            <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h5 class="fw-bold mb-1 text-zinc-900">
                        <i class="bi bi-shield-lock-fill text-primary me-2"></i> Role-Based Access Control (RBAC)
                    </h5>
                    <p class="text-muted small mb-0">
                        Powered by <code>spatie/laravel-permission</code>. Manage roles, configure granular permissions, and control Bulk Mail access independently for any role.
                    </p>
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetPermissionCache()">
                        <i class="bi bi-arrow-repeat me-1"></i> Flush Cache
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                        <i class="bi bi-shield-plus me-1"></i> Create Role
                    </button>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createPermissionModal">
                        <i class="bi bi-key-fill me-1"></i> Create Permission
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Role Summary Cards -->
    <div class="col-12">
        <div class="row g-3" id="role-summary-cards">
            <!-- Rendered dynamically by JS -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-danger-soft px-2.5 py-1.5 fw-semibold">Admin</span>
                            <span class="badge bg-success-soft"><i class="bi bi-check-circle me-1"></i>Full Access</span>
                        </div>
                        <h6 class="fw-bold mb-1">Administrator</h6>
                        <p class="text-muted small mb-2">Super-admin with all permissions including full user, infrastructure, and bulk email access.</p>
                        <div class="small fw-semibold text-zinc-600">Bulk Mail: <span class="text-success fw-bold"><i class="bi bi-check-lg"></i> Enabled</span></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-success-soft px-2.5 py-1.5 fw-semibold">Manager</span>
                            <span class="badge bg-primary-soft" id="manager-bulk-badge">Configurable</span>
                        </div>
                        <h6 class="fw-bold mb-1">Team Manager</h6>
                        <p class="text-muted small mb-2">Approves team campaigns, supervises assigned direct reports, and manages team activities.</p>
                        <div class="small fw-semibold text-zinc-600">Bulk Mail: <span id="manager-bulk-status" class="fw-bold">Checking...</span></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-secondary-soft px-2.5 py-1.5 fw-semibold">Employee</span>
                            <span class="badge bg-primary-soft" id="employee-bulk-badge">Configurable</span>
                        </div>
                        <h6 class="fw-bold mb-1">Employee (Standard Sender)</h6>
                        <p class="text-muted small mb-2">Mapped from standard user accounts. Sends campaigns and manages templates and signatures.</p>
                        <div class="small fw-semibold text-zinc-600">Bulk Mail: <span id="employee-bulk-status" class="fw-bold">Checking...</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Permission Matrix Table -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h6 class="mb-0 fw-bold text-zinc-900">
                        <i class="bi bi-grid-3x3-gap-fill text-zinc-500 me-2"></i> Role & Permission Matrix
                    </h6>
                    <small class="text-muted">Check or uncheck permissions to configure access per role. Bulk Mail access is not tied to any role and can be granted or revoked at will.</small>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="loadMatrixData()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="matrix-table">
                        <thead class="table-light">
                            <tr id="matrix-header-row">
                                <th class="ps-4 py-3" style="min-width: 160px;">Role</th>
                                <th class="text-center py-3">Loading Permissions...</th>
                                <th class="text-end pe-4 py-3" style="min-width: 140px;">Save Changes</th>
                            </tr>
                        </thead>
                        <tbody id="matrix-body">
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading permissions matrix...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white py-3 px-4 border-top text-muted small d-flex justify-content-between align-items-center">
                <span><i class="bi bi-info-circle me-1 text-primary"></i> Administrator role has implicit full bypass and maintains all permissions automatically.</span>
                <span class="badge bg-light text-dark border"><i class="bi bi-shield-check text-success me-1"></i> Spatie Synced</span>
            </div>
        </div>
    </div>

    <!-- User Role Assignment Panel -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h6 class="mb-0 fw-bold text-zinc-900">
                        <i class="bi bi-people-fill text-zinc-500 me-2"></i> User Role Assignments
                    </h6>
                    <small class="text-muted">Synchronize each user record with their primary Spatie role (Admin, Manager, Employee). Changes take effect immediately.</small>
                </div>
                <span class="badge bg-secondary-soft" id="user-count-badge">0 Users</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 py-3">User</th>
                                <th class="py-3">Email Address</th>
                                <th class="py-3">Current Spatie Role</th>
                                <th class="py-3">Legacy Role Column</th>
                                <th class="py-3" style="width: 260px;">Change Role</th>
                                <th class="text-end pe-4 py-3" style="width: 140px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="users-role-table-body">
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading user assignments...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Permissions Registry & Catalog Card -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
                <div>
                    <h6 class="mb-0 fw-bold text-zinc-900">
                        <i class="bi bi-key-fill text-success me-2"></i> Permissions Catalog & Route Access
                    </h6>
                    <small class="text-muted">Master list of registered application and route permissions. Control access to endpoints like <code>/campaign/new</code>.</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary-soft" id="perm-count-badge">0 Permissions</span>
                    <button type="button" class="btn btn-success btn-xs" data-bs-toggle="modal" data-bs-target="#createPermissionModal">
                        <i class="bi bi-plus-circle me-1"></i> New Permission
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 py-2.5" style="width: 260px;">Permission Name</th>
                                <th class="py-2.5" style="min-width: 220px;">Category & Description</th>
                                <th class="py-2.5" style="width: 100px;">Guard</th>
                                <th class="py-2.5" style="min-width: 240px;">Granted To Roles</th>
                                <th class="text-end pe-4 py-2.5" style="width: 120px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="permissions-catalog-body">
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading permissions catalog...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create New Role -->
<div class="modal fade" id="createRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-3 px-4 border-bottom">
                <h6 class="modal-title fw-bold text-zinc-900"><i class="bi bi-plus-circle me-1.5 text-primary"></i> Create Application Role</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.75rem;"></button>
            </div>
            <div class="modal-body py-3 px-4">
                <form id="create-role-form" onsubmit="submitCreateRole(event)">
                    <div class="mb-3">
                        <label for="new-role-name" class="form-label mb-1 fw-semibold">Role Name</label>
                        <input type="text" class="form-control" id="new-role-name" placeholder="e.g. SalesDirector, Auditor" required>
                        <div class="form-text small">Use PascalCase or standard alphanumeric names. Guard will be set to 'web'.</div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-save-new-role">
                            <i class="bi bi-check-lg me-1"></i> Create Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create New Permission -->
<div class="modal fade" id="createPermissionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-3 px-4 border-bottom">
                <h6 class="modal-title fw-bold text-zinc-900">
                    <i class="bi bi-key-fill me-1.5 text-success"></i> Create Application Permission
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.75rem;"></button>
            </div>
            <div class="modal-body py-3 px-4">
                <form id="create-permission-form" onsubmit="submitCreatePermission(event)">
                    <div class="mb-3">
                        <label for="new-permission-name" class="form-label mb-1 fw-semibold">Permission Name</label>
                        <input type="text" class="form-control font-monospace" id="new-permission-name" placeholder="e.g. campaign.new or /campaign/new" required>
                        <div class="form-text small">Standard syntax: dot notation (<code>campaign.new</code>) or route path (<code>/campaign/new</code>).</div>
                    </div>

                    <!-- Quick Preset suggestions -->
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1 d-block">Quick Presets / Suggestions:</label>
                        <div class="d-flex flex-wrap gap-1.5">
                            <button type="button" class="btn btn-outline-secondary btn-xs font-monospace" onclick="setPermissionInput('/campaign/new')">/campaign/new</button>
                            <button type="button" class="btn btn-outline-secondary btn-xs font-monospace" onclick="setPermissionInput('campaign.new')">campaign.new</button>
                            <button type="button" class="btn btn-outline-secondary btn-xs font-monospace" onclick="setPermissionInput('campaign.create')">campaign.create</button>
                            <button type="button" class="btn btn-outline-secondary btn-xs font-monospace" onclick="setPermissionInput('campaign.export')">campaign.export</button>
                            <button type="button" class="btn btn-outline-secondary btn-xs font-monospace" onclick="setPermissionInput('campaign.delete')">campaign.delete</button>
                        </div>
                    </div>

                    <!-- Role grants -->
                    <div class="mb-3">
                        <label class="form-label mb-1 fw-semibold small">Grant To Roles On Creation</label>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="grant-perm-admin" checked disabled>
                            <label class="form-check-label small text-zinc-800" for="grant-perm-admin">
                                <strong>Admin</strong> <span class="text-muted">(Super-admin, auto-granted)</span>
                            </label>
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="grant-perm-manager" name="role_grants" value="Manager">
                            <label class="form-check-label small text-zinc-800" for="grant-perm-manager">
                                <strong>Manager</strong> (Team Manager)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="grant-perm-employee" name="role_grants" value="Employee">
                            <label class="form-check-label small text-zinc-800" for="grant-perm-employee">
                                <strong>Employee</strong> (Standard Sender)
                            </label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm" id="btn-save-new-perm">
                            <i class="bi bi-check-lg me-1"></i> Create Permission
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('extra_scripts')
<script>
    let matrixData = {
        roles: [],
        all_permissions: [],
        grouped_permissions: {},
        users: []
    };

    let matrixColumns = [];

    document.addEventListener('DOMContentLoaded', () => {
        loadMatrixData();
    });

    function loadMatrixData() {
        fetch('{{ route("api.admin.roles_permissions") }}')
            .then(res => res.json())
            .then(data => {
                matrixData = data;
                computeMatrixColumns();
                renderMatrixHeaders();
                renderMatrix();
                renderPermissionsCatalog();
                renderUserAssignments();
                updateSummaryCards();
            })
            .catch(err => {
                console.error(err);
                showToast('danger', 'Failed to load role and permission data.');
            });
    }

    function computeMatrixColumns() {
        if (!matrixData.all_permissions || matrixData.all_permissions.length === 0) {
            matrixColumns = [];
            return;
        }

        const priorityOrder = [
            'bulk-mail',
            'bulk-mail.view',
            'bulk-mail.create',
            'bulk-mail.send',
            'campaign.new',
            '/campaign/new',
            'campaign.create',
            'bulk-mail.manage',
            'team-campaigns.view',
            'signatures.manage',
            'templates.manage',
            'inbound-replies.view',
            'users.manage',
            'infrastructure.manage',
        ];

        const allNames = matrixData.all_permissions.map(p => p.name);

        matrixColumns = allNames.sort((a, b) => {
            const idxA = priorityOrder.indexOf(a);
            const idxB = priorityOrder.indexOf(b);
            if (idxA !== -1 && idxB !== -1) return idxA - idxB;
            if (idxA !== -1) return -1;
            if (idxB !== -1) return 1;
            return a.localeCompare(b);
        });
    }

    function renderMatrixHeaders() {
        const headerRow = document.getElementById('matrix-header-row');
        if (!headerRow) return;

        let thHtml = `<th class="ps-4 py-3" style="min-width: 160px;">Role</th>`;

        matrixColumns.forEach(col => {
            let label = col;
            let sub = '';
            let isHighlight = false;

            if (col === 'bulk-mail') {
                sub = '(All Bulk Access)';
                isHighlight = true;
            } else if (col === 'bulk-mail.view') {
                sub = '(View Tool)';
            } else if (col === 'bulk-mail.create') {
                sub = '(Compose)';
            } else if (col === 'bulk-mail.send') {
                sub = '(Dispatch)';
            } else if (col === 'campaign.new' || col === '/campaign/new') {
                sub = '(New Campaign UI)';
                isHighlight = true;
            } else if (col === 'campaign.create') {
                sub = '(Campaign Create)';
            } else if (col === 'team-campaigns.view') {
                sub = '(Team Approval)';
            } else if (col === 'signatures.manage') {
                sub = '(Signatures)';
            } else if (col === 'templates.manage') {
                sub = '(Templates)';
            } else if (col === 'inbound-replies.view') {
                sub = '(Inbound Replies)';
            } else if (col === 'users.manage') {
                sub = '(User Admin)';
            } else if (col === 'infrastructure.manage') {
                sub = '(Infrastructure)';
            } else {
                sub = '(Custom)';
            }

            const headerColor = isHighlight ? 'text-primary' : 'text-zinc-800';

            thHtml += `
                <th class="py-3 text-center" style="min-width: 135px;" title="Permission: ${escapeHtml(col)}">
                    <div class="fw-bold ${headerColor} text-truncate" style="font-size: 0.8rem;">
                        ${(col.startsWith('/') || col.includes('campaign')) ? '<i class="bi bi-pencil-square me-1"></i>' : ''}
                        ${escapeHtml(label)}
                    </div>
                    <div class="small text-muted" style="font-size: 0.68rem;">${sub}</div>
                </th>
            `;
        });

        thHtml += `<th class="text-end pe-4 py-3" style="min-width: 140px;">Save Changes</th>`;

        headerRow.innerHTML = thHtml;
    }

    function renderMatrix() {
        const tbody = document.getElementById('matrix-body');
        if (!tbody) return;

        if (matrixData.roles.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">No roles found.</td></tr>';
            return;
        }

        let html = '';

        matrixData.roles.forEach(role => {
            const rolePermNames = (role.permissions || []).map(p => p.name);
            const isAdmin = role.name === 'Admin';

            let roleBadge = '';
            if (role.name === 'Admin') {
                roleBadge = '<span class="badge bg-danger-soft fw-semibold px-2 py-1"><i class="bi bi-shield-fill me-1"></i>Admin</span>';
            } else if (role.name === 'Manager') {
                roleBadge = '<span class="badge bg-success-soft fw-semibold px-2 py-1"><i class="bi bi-person-workspace me-1"></i>Manager</span>';
            } else if (role.name === 'Employee') {
                roleBadge = '<span class="badge bg-secondary-soft fw-semibold px-2 py-1"><i class="bi bi-person me-1"></i>Employee</span>';
            } else {
                roleBadge = `<span class="badge bg-primary-soft fw-semibold px-2 py-1">${escapeHtml(role.name)}</span>`;
            }

            html += `<tr>
                <td class="ps-4 py-3">
                    <div class="d-flex align-items-center gap-2">
                        ${roleBadge}
                    </div>
                    <div class="small text-muted mt-0.5" style="font-size: 0.72rem;">Guard: ${role.guard_name}</div>
                </td>`;

            matrixColumns.forEach(col => {
                const isChecked = isAdmin || rolePermNames.includes(col);
                const isHighlightCol = col.startsWith('bulk-mail') || col.includes('campaign');
                const checkboxClass = isHighlightCol ? 'form-check-input border-primary' : 'form-check-input';
                const safeColId = col.replace(/[^a-zA-Z0-9]/g, '-');

                html += `<td class="text-center py-3">
                    <div class="form-check form-switch d-inline-block">
                        <input class="${checkboxClass}" 
                               type="checkbox" 
                               id="perm-${role.id}-${safeColId}" 
                               data-role-id="${role.id}" 
                               data-perm="${escapeHtml(col)}" 
                               ${isChecked ? 'checked' : ''}
                               ${isAdmin ? 'disabled title="Admin role has full access automatically"' : ''}
                               onchange="onPermissionToggled(${role.id}, '${escapeHtml(col)}', this.checked)">
                    </div>
                </td>`;
            });

            html += `<td class="text-end pe-4 py-3">
                ${isAdmin ? 
                    '<span class="badge bg-light text-muted border"><i class="bi bi-lock me-1"></i>Locked (All)</span>' : 
                    `<button class="btn btn-primary btn-xs" id="btn-save-role-${role.id}" onclick="saveRolePermissions(${role.id})">
                        <i class="bi bi-save me-1"></i> Save
                    </button>`
                }
            </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    function onPermissionToggled(roleId, perm, checked) {
        // If master 'bulk-mail' is checked, auto-check view/create/send for better UX
        if (perm === 'bulk-mail' && checked) {
            ['bulk-mail.view', 'bulk-mail.create', 'bulk-mail.send'].forEach(sub => {
                const safeSub = sub.replace(/[^a-zA-Z0-9]/g, '-');
                const el = document.getElementById(`perm-${roleId}-${safeSub}`);
                if (el) el.checked = true;
            });
        }
    }

    function saveRolePermissions(roleId) {
        const role = matrixData.roles.find(r => r.id === roleId);
        if (!role) return;

        const btn = document.getElementById(`btn-save-role-${roleId}`);
        const originalBtnText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
        }

        // Collect all checked permissions for this role
        const selectedPermissions = [];
        matrixColumns.forEach(col => {
            const safeColId = col.replace(/[^a-zA-Z0-9]/g, '-');
            const el = document.getElementById(`perm-${roleId}-${safeColId}`);
            if (el && el.checked) {
                selectedPermissions.push(col);
            }
        });

        fetch(`/api/admin/roles/${roleId}/permissions`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ permissions: selectedPermissions })
        })
        .then(res => res.json())
        .then(res => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalBtnText;
            }
            if (res.success) {
                showToast('success', res.message || `Permissions for ${role.name} updated!`);
                role.permissions = selectedPermissions.map(p => ({ name: p }));
                renderPermissionsCatalog();
                updateSummaryCards();
            } else {
                showToast('danger', res.error || 'Failed to save permissions.');
            }
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalBtnText;
            }
            console.error(err);
            showToast('danger', 'An error occurred while saving permissions.');
        });
    }

    function renderPermissionsCatalog() {
        const tbody = document.getElementById('permissions-catalog-body');
        const countBadge = document.getElementById('perm-count-badge');
        if (!tbody) return;

        if (countBadge) {
            countBadge.textContent = `${matrixData.all_permissions.length} Permissions`;
        }

        if (!matrixData.all_permissions || matrixData.all_permissions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No permissions found.</td></tr>';
            return;
        }

        const corePerms = [
            'bulk-mail',
            'bulk-mail.view',
            'bulk-mail.create',
            'bulk-mail.send',
            'bulk-mail.manage',
            'users.manage',
            'infrastructure.manage',
        ];

        let html = '';

        matrixColumns.forEach(col => {
            const permObj = matrixData.all_permissions.find(p => p.name === col);
            if (!permObj) return;

            const rolesWithPerm = matrixData.roles.filter(r => {
                if (r.name === 'Admin') return true;
                return (r.permissions || []).some(p => p.name === col);
            });

            let categoryDesc = 'Application Tool';
            let categoryBadge = '<span class="badge bg-secondary-soft me-1">Tool</span>';

            if (col.startsWith('bulk-mail')) {
                categoryDesc = 'Bulk Email Campaigns & Dispatch';
                categoryBadge = '<span class="badge bg-primary-soft me-1">Bulk Mail</span>';
            } else if (col === 'campaign.new' || col === '/campaign/new' || col.startsWith('campaign.')) {
                categoryDesc = 'Direct route permission for /campaign/new creation view';
                categoryBadge = '<span class="badge bg-success-soft me-1">Route / Campaign</span>';
            } else if (col.includes('signatures') || col.includes('templates') || col.includes('replies')) {
                categoryDesc = 'Messaging & Composer Resources';
                categoryBadge = '<span class="badge bg-info-soft me-1">Resource</span>';
            } else if (col.includes('manage') || col.includes('users') || col.includes('infrastructure')) {
                categoryDesc = 'Administration & Security';
                categoryBadge = '<span class="badge bg-danger-soft me-1">Admin</span>';
            }

            const rolesBadges = rolesWithPerm.map(r => {
                if (r.name === 'Admin') return '<span class="badge bg-danger-soft me-1">Admin</span>';
                if (r.name === 'Manager') return '<span class="badge bg-success-soft me-1">Manager</span>';
                if (r.name === 'Employee') return '<span class="badge bg-secondary-soft me-1">Employee</span>';
                return `<span class="badge bg-primary-soft me-1">${escapeHtml(r.name)}</span>`;
            }).join('') || '<span class="text-muted small fst-italic">None</span>';

            const isCore = corePerms.includes(col);

            html += `<tr>
                <td class="ps-4 py-2.5">
                    <div class="d-flex align-items-center gap-1.5">
                        <code class="fw-bold text-zinc-900">${escapeHtml(col)}</code>
                        ${(col === 'campaign.new' || col === '/campaign/new') ? '<span class="badge bg-warning-soft text-amber-800" style="font-size:0.65rem;">New</span>' : ''}
                    </div>
                </td>
                <td class="py-2.5 small text-secondary">
                    ${categoryBadge} ${categoryDesc}
                </td>
                <td class="py-2.5 small font-monospace text-muted">
                    ${escapeHtml(permObj.guard_name || 'web')}
                </td>
                <td class="py-2.5">
                    ${rolesBadges}
                </td>
                <td class="text-end pe-4 py-2.5">
                    ${isCore ? 
                        '<span class="badge bg-light text-muted border small"><i class="bi bi-lock me-1"></i>Core</span>' :
                        `<button type="button" class="btn btn-outline-danger btn-xs" onclick="deletePermission(${permObj.id}, '${escapeHtml(col)}')">
                            <i class="bi bi-trash me-1"></i> Delete
                        </button>`
                    }
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    function setPermissionInput(val) {
        const input = document.getElementById('new-permission-name');
        if (input) {
            input.value = val;
            input.focus();
        }
    }

    function submitCreatePermission(e) {
        e.preventDefault();
        const input = document.getElementById('new-permission-name');
        const btn = document.getElementById('btn-save-new-perm');
        const name = input.value.trim();

        if (!name) return;

        const selectedRoles = [];
        document.querySelectorAll('input[name="role_grants"]:checked').forEach(cb => {
            selectedRoles.push(cb.value);
        });

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';

        fetch('{{ route("api.admin.permissions.create") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ 
                name: name,
                roles: selectedRoles
            })
        })
        .then(res => res.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Create Permission';

            if (res.success) {
                showToast('success', res.message || `Permission '${name}' created successfully.`);
                input.value = '';
                const modal = bootstrap.Modal.getInstance(document.getElementById('createPermissionModal'));
                if (modal) modal.hide();
                loadMatrixData();
            } else {
                showToast('danger', res.error || 'Failed to create permission.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Create Permission';
            console.error(err);
            showToast('danger', 'An error occurred while creating permission.');
        });
    }

    function deletePermission(id, name) {
        if (!confirm(`Are you sure you want to delete permission '${name}'? This will remove it from all roles.`)) {
            return;
        }

        fetch(`/api/admin/permissions/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                showToast('success', res.message || `Permission '${name}' deleted.`);
                loadMatrixData();
            } else {
                showToast('danger', res.error || 'Failed to delete permission.');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('danger', 'An error occurred while deleting permission.');
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderUserAssignments() {
        const tbody = document.getElementById('users-role-table-body');
        const countBadge = document.getElementById('user-count-badge');
        if (!tbody) return;

        if (countBadge) {
            countBadge.textContent = `${matrixData.users.length} Users`;
        }

        if (matrixData.users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No users found.</td></tr>';
            return;
        }

        let html = '';
        matrixData.users.forEach(user => {
            const currentRole = user.spatie_role || 'Employee';

            let roleBadge = '';
            if (currentRole === 'Admin') {
                roleBadge = '<span class="badge bg-danger-soft"><i class="bi bi-shield-fill me-1"></i>Admin</span>';
            } else if (currentRole === 'Manager') {
                roleBadge = '<span class="badge bg-success-soft"><i class="bi bi-person-workspace me-1"></i>Manager</span>';
            } else {
                roleBadge = '<span class="badge bg-secondary-soft"><i class="bi bi-person me-1"></i>Employee</span>';
            }

            const initial = (user.username || '?').substring(0, 1).toUpperCase();

            // Build role options dropdown
            let optionsHtml = '';
            matrixData.roles.forEach(r => {
                const isSelected = r.name.toLowerCase() === currentRole.toLowerCase();
                optionsHtml += `<option value="${r.name}" ${isSelected ? 'selected' : ''}>${r.name}</option>`;
            });

            html += `<tr>
                <td class="ps-4 py-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-zinc-100 text-zinc-700 fw-bold d-flex align-items-center justify-content-center me-2.5" 
                             style="width: 32px; height: 32px; font-size: 0.8rem; border: 1px solid var(--border-color);">
                            ${initial}
                        </div>
                        <div>
                            <div class="fw-semibold text-zinc-900">${user.username}</div>
                            <div class="text-muted small" style="font-size: 0.75rem;">${user.name || user.username}</div>
                        </div>
                    </div>
                </td>
                <td class="py-3 text-zinc-600 font-monospace small">${user.email}</td>
                <td class="py-3" id="user-role-badge-${user.id}">${roleBadge}</td>
                <td class="py-3"><span class="badge bg-light text-muted border">${user.legacy_role}</span></td>
                <td class="py-3">
                    <select class="form-select form-select-sm" id="user-role-select-${user.id}">
                        ${optionsHtml}
                    </select>
                </td>
                <td class="text-end pe-4 py-3">
                    <button class="btn btn-outline-primary btn-xs" id="btn-assign-${user.id}" onclick="assignUserRole(${user.id})">
                        <i class="bi bi-arrow-repeat me-1"></i> Update
                    </button>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    function assignUserRole(userId) {
        const select = document.getElementById(`user-role-select-${userId}`);
        const btn = document.getElementById(`btn-assign-${userId}`);
        if (!select || !btn) return;

        const chosenRole = select.value;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

        fetch(`/api/admin/users/${userId}/assign-role`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ role: chosenRole })
        })
        .then(res => res.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = originalText;

            if (res.success) {
                showToast('success', res.message || 'User role updated successfully.');
                
                // Update badge in table
                const badgeEl = document.getElementById(`user-role-badge-${userId}`);
                if (badgeEl) {
                    let newBadge = '';
                    if (chosenRole === 'Admin') {
                        newBadge = '<span class="badge bg-danger-soft"><i class="bi bi-shield-fill me-1"></i>Admin</span>';
                    } else if (chosenRole === 'Manager') {
                        newBadge = '<span class="badge bg-success-soft"><i class="bi bi-person-workspace me-1"></i>Manager</span>';
                    } else {
                        newBadge = '<span class="badge bg-secondary-soft"><i class="bi bi-person me-1"></i>Employee</span>';
                    }
                    badgeEl.innerHTML = newBadge;
                }

                // Update local memory
                const userObj = matrixData.users.find(u => u.id === userId);
                if (userObj) {
                    userObj.spatie_role = chosenRole;
                }
            } else {
                showToast('danger', res.error || 'Failed to update user role.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            console.error(err);
            showToast('danger', 'An error occurred while updating user role.');
        });
    }

    function submitCreateRole(e) {
        e.preventDefault();
        const input = document.getElementById('new-role-name');
        const btn = document.getElementById('btn-save-new-role');
        const name = input.value.trim();

        if (!name) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';

        fetch('{{ route("api.admin.roles.create") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ name: name })
        })
        .then(res => res.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Create Role';

            if (res.success) {
                showToast('success', res.message || `Role ${name} created.`);
                input.value = '';
                const modal = bootstrap.Modal.getInstance(document.getElementById('createRoleModal'));
                if (modal) modal.hide();
                loadMatrixData();
            } else {
                showToast('danger', res.error || 'Failed to create role.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Create Role';
            console.error(err);
            showToast('danger', 'An error occurred while creating role.');
        });
    }

    function resetPermissionCache() {
        fetch('{{ route("api.admin.roles.reset_cache") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                showToast('success', 'Spatie permission cache has been flushed successfully.');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('danger', 'Failed to flush permission cache.');
        });
    }
</script>
@endsection
