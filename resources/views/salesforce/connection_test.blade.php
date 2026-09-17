@extends('layouts.app')

@section('page_title', 'Salesforce Connection Test')

@section('content')
<div class="container py-3" style="max-width: 900px;">
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-zinc-900 d-flex align-items-center gap-2">
                <i class="bi bi-shield-check text-primary"></i> Salesforce Connection Test (JSforce)
            </h5>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('salesforce.leads') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-person-lines-fill me-1"></i> View Leads Directory
                </a>
                <button class="btn btn-primary btn-sm shadow-sm d-flex align-items-center gap-1.5" id="test-btn" onclick="runConnectionTest()">
                    <i class="bi bi-arrow-clockwise" id="test-icon"></i>
                    <span>Test Connection</span>
                </button>
            </div>
        </div>

        <div class="card-body p-4">
            <!-- Loading State -->
            <div id="conn-loading" class="text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 2.5rem; height: 2.5rem;"></div>
                <h6 class="fw-semibold text-zinc-800 mb-1">Testing Salesforce Connection...</h6>
                <p class="text-secondary small mb-0">Connecting to Salesforce login endpoint using JSforce with credentials from <code>.env</code></p>
            </div>

            <!-- Success State Box -->
            <div id="conn-success" class="d-none">
                <div class="p-4 rounded-3 border mb-3" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724;">
                    <h4 class="fw-bold mb-3 d-flex align-items-center gap-2" style="color: #155724; font-size: 1.25rem;">
                        <i class="bi bi-check-circle-fill"></i> Salesforce Connected Successfully!
                    </h4>

                    <div class="mb-2">
                        <strong>Username:</strong> <span id="res-username" class="font-monospace text-dark"></span>
                    </div>

                    <div class="mb-2">
                        <strong>HTTP Status:</strong> <span id="res-http-code" class="badge bg-success">200</span>
                    </div>

                    <div class="mb-2">
                        <strong>Salesforce Server:</strong><br>
                        <span id="res-server-url" class="font-monospace small text-dark d-block text-break mt-1 bg-white bg-opacity-50 p-2 rounded border border-success-subtle"></span>
                    </div>

                    <div class="mb-2">
                        <strong>Organization ID:</strong> <span id="res-org-id" class="font-monospace text-dark"></span>
                    </div>

                    <div class="mb-2">
                        <strong>User ID:</strong> <span id="res-user-id" class="font-monospace text-dark"></span>
                    </div>

                    <div class="mb-0">
                        <strong>Session ID (Access Token):</strong><br>
                        <span id="res-session-id" class="font-monospace small text-dark d-block text-break mt-1 bg-white bg-opacity-50 p-2 rounded border border-success-subtle"></span>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button class="btn btn-outline-secondary btn-sm" onclick="runConnectionTest()">
                        <i class="bi bi-arrow-repeat me-1"></i> Retest
                    </button>
                    <a href="{{ route('salesforce.leads') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-person-lines-fill me-1"></i> Open Salesforce Leads Table &rarr;
                    </a>
                </div>
            </div>

            <!-- Error State Box -->
            <div id="conn-error" class="d-none">
                <div class="p-4 rounded-3 border mb-3" style="background-color: #f8d7da; border-color: #f5c6cb; color: #721c24;">
                    <h4 class="fw-bold mb-2 d-flex align-items-center gap-2" style="color: #721c24; font-size: 1.25rem;">
                        <i class="bi bi-x-circle-fill"></i> Salesforce Connection Failed
                    </h4>

                    <div class="mb-2">
                        <strong>HTTP Status:</strong> <span id="err-http-code" class="badge bg-danger"></span>
                    </div>

                    <div class="mb-2">
                        <strong>Error Message:</strong><br>
                        <div id="err-message" class="mt-1 small bg-white bg-opacity-75 p-2.5 rounded border border-danger-subtle font-monospace text-danger"></div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 border">
                    <h6 class="fw-semibold text-zinc-800 mb-1">Configuration Checklist:</h6>
                    <ul class="small text-secondary mb-0 ps-3">
                        <li>Verify <code>SF_USERNAME</code> and <code>SF_PASSWORD</code> in your <code>.env</code> file.</li>
                        <li>Ensure <code>SF_PASSWORD</code> contains your Salesforce password immediately followed by your Security Token.</li>
                        <li>Verify <code>SF_LOGIN_URL</code> (use <code>https://login.salesforce.com</code> or your custom My Domain).</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('extra_scripts')
<script>
    async function runConnectionTest() {
        const loading = document.getElementById('conn-loading');
        const successBox = document.getElementById('conn-success');
        const errorBox = document.getElementById('conn-error');
        const testIcon = document.getElementById('test-icon');

        loading.classList.remove('d-none');
        successBox.classList.add('d-none');
        errorBox.classList.add('d-none');
        if (testIcon) testIcon.classList.add('bi-spin');

        try {
            const res = await fetch('/api/salesforce/test-connection', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await res.json();

            loading.classList.add('d-none');

            if (data.success) {
                document.getElementById('res-username').innerText = data.username || 'pramod@retrotech.in';
                document.getElementById('res-http-code').innerText = data.httpCode || 200;
                document.getElementById('res-server-url').innerText = data.serverUrl || data.instanceUrl || 'N/A';
                document.getElementById('res-org-id').innerText = data.organizationId || 'N/A';
                document.getElementById('res-user-id').innerText = data.userId || 'N/A';
                document.getElementById('res-session-id').innerText = data.maskedSessionId || 'N/A';

                successBox.classList.remove('d-none');
            } else {
                document.getElementById('err-http-code').innerText = data.httpCode || res.status || 500;
                document.getElementById('err-message').innerText = data.error || 'Salesforce connection failed.';

                errorBox.classList.remove('d-none');
            }
        } catch (err) {
            loading.classList.add('d-none');
            document.getElementById('err-http-code').innerText = 500;
            document.getElementById('err-message').innerText = 'Network error: ' + (err.message || String(err));
            errorBox.classList.remove('d-none');
        } finally {
            if (testIcon) testIcon.classList.remove('bi-spin');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        runConnectionTest();
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
</style>
@endsection
