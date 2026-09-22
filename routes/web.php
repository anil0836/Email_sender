<?php

use App\Http\Controllers\Admin\AdminRolePermissionController;
use App\Http\Controllers\Admin\SalesforceAccountAdminController;
use App\Http\Controllers\Admin\SalesforceContactAdminController;
use App\Http\Controllers\Admin\SalesforceLeadAdminController;
use App\Http\Controllers\Admin\EmailSuppressionAdminController;
use App\Http\Controllers\Admin\EmailSuppressionImportController;
use App\Http\Controllers\Admin\SalesforceSfUserAdminController;
use App\Http\Controllers\Admin\SalesforceUserAdminController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\RepliesController;
use App\Http\Controllers\PabblyWebhookController;
use App\Http\Controllers\SalesforceLeadController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\SimulatorController;
use App\Http\Controllers\TemplateSignatureController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

// --- PUBLIC AUTHENTICATION ROUTES ---
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

// --- PUBLIC EMAIL TRACKING & WEBHOOK ROUTES ---
Route::get('/track/open/{token}', [TrackingController::class, 'trackOpen'])->name('track_open');
Route::get('/track/click/{token}', [TrackingController::class, 'trackClick'])->name('track_click');
Route::match(['get', 'post'], '/track/unsubscribe/{token}', [TrackingController::class, 'trackUnsubscribe'])->name('track_unsubscribe');
Route::post('/api/simulator/trigger-webhook', [SimulatorController::class, 'apiTriggerWebhook'])->name('provider_webhook');
Route::post('/api/webhooks/pabbly', [PabblyWebhookController::class, 'handleWebhook'])->name('pabbly.webhook');

// --- AUTHENTICATED WEB UI ROUTES ---
Route::middleware(['app_auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard_view');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Bulk Mail & Campaign Creation Routes (Protected by Spatie Bulk-Mail & Campaign permissions)
    Route::middleware(['permission:bulk-mail|bulk-mail.create|campaign.new|campaign.create|/campaign/new'])->group(function () {
        Route::get('/campaign/new', [CampaignController::class, 'createView'])->name('campaign_create_view');
        Route::get('/campaign/create', [CampaignController::class, 'createView']);
    });
    Route::middleware(['permission:bulk-mail|bulk-mail.view'])->group(function () {
        Route::get('/campaign/bulk', [CampaignController::class, 'bulkEmailView'])->name('campaign_bulk_view');
    });

    Route::get('/campaign/list', [CampaignController::class, 'listView'])->name('campaign_list_view');
    Route::get('/campaign/{campaign_id}', [CampaignController::class, 'detailView'])->name('campaign_detail_view');
    
    // Signatures UI & Management
    Route::get('/signatures', [SignatureController::class, 'index'])->name('signatures.index');
    Route::post('/signatures', [SignatureController::class, 'store'])->name('signatures.store');
    Route::get('/signatures/{id}', [SignatureController::class, 'show'])->name('signatures.show');
    Route::put('/signatures/{id}', [SignatureController::class, 'update'])->name('signatures.update');
    Route::delete('/signatures/{id}', [SignatureController::class, 'destroy'])->name('signatures.destroy');
    Route::post('/signatures/{id}/default', [SignatureController::class, 'setDefault'])->name('signatures.default');

    Route::get('/salesforce/leads', [SalesforceLeadController::class, 'index'])->name('salesforce.leads');
    Route::get('/salesforce/test-connection', [SalesforceLeadController::class, 'connectionTestView'])->name('salesforce.connection_test');
    Route::get('/replies', [RepliesController::class, 'index'])->name('replies_view');
    Route::get('/simulator', [SimulatorController::class, 'index'])->name('simulator_view');
    Route::get('/download/attachment/{campaign_id}/{filename}', [CampaignController::class, 'downloadAttachment'])->name('download_attachment');

    // Manager and Admin UI
    Route::middleware(['app_auth:admin,manager'])->group(function () {
        Route::get('/manager/campaigns', [ManagerController::class, 'campaignsView'])->name('team_campaigns_view');
    });

    // Admin-only UI & Management
    Route::middleware(['app_auth:admin'])->group(function () {
        Route::get('/admin/users', [AdminController::class, 'usersView'])->name('admin_users_view');
        Route::get('/admin/infrastructure', [AdminController::class, 'infrastructureView'])->name('admin_infrastructure_view');

        // Salesforce Sync Management
        Route::get('/admin/salesforce-leads', [SalesforceLeadAdminController::class, 'index'])->name('admin.salesforce_leads.index');
        Route::get('/admin/salesforce-leads/{lead}', [SalesforceLeadAdminController::class, 'show'])->name('admin.salesforce_leads.show');
        Route::post('/admin/salesforce-leads/sync', [SalesforceLeadAdminController::class, 'manualSync'])->name('admin.salesforce_leads.sync');
        Route::get('/admin/salesforce-leads/sync-status', [SalesforceLeadAdminController::class, 'syncStatus'])->name('admin.salesforce_leads.status');

        Route::get('/admin/salesforce-accounts', [SalesforceAccountAdminController::class, 'index'])->name('admin.salesforce_accounts.index');
        Route::post('/admin/salesforce-accounts/sync', [SalesforceAccountAdminController::class, 'manualSync'])->name('admin.salesforce_accounts.sync');
        Route::get('/admin/salesforce-accounts/sync-status', [SalesforceAccountAdminController::class, 'syncStatus'])->name('admin.salesforce_accounts.status');

        Route::get('/admin/salesforce-contacts', [SalesforceContactAdminController::class, 'index'])->name('admin.salesforce_contacts.index');
        Route::post('/admin/salesforce-contacts/sync', [SalesforceContactAdminController::class, 'manualSync'])->name('admin.salesforce_contacts.sync');
        Route::get('/admin/salesforce-contacts/sync-status', [SalesforceContactAdminController::class, 'syncStatus'])->name('admin.salesforce_contacts.status');

        Route::get('/admin/salesforce-users', [SalesforceUserAdminController::class, 'index'])->name('admin.salesforce_users.index');
        Route::post('/admin/salesforce-users/sync', [SalesforceUserAdminController::class, 'manualSync'])->name('admin.salesforce_users.sync');
        Route::get('/admin/salesforce-users/sync-status', [SalesforceUserAdminController::class, 'syncStatus'])->name('admin.salesforce_users.status');

        Route::get('/admin/salesforce-sf-users', [SalesforceSfUserAdminController::class, 'index'])->name('admin.salesforce_sf_users.index');
        Route::post('/admin/salesforce-sf-users/sync', [SalesforceSfUserAdminController::class, 'manualSync'])->name('admin.salesforce_sf_users.sync');
        Route::get('/admin/salesforce-sf-users/sync-status', [SalesforceSfUserAdminController::class, 'syncStatus'])->name('admin.salesforce_sf_users.status');

        // Role & Permission Management (Spatie)
        Route::get('/admin/roles-permissions', [AdminRolePermissionController::class, 'index'])->name('admin.roles.index');
        Route::get('/api/admin/roles-permissions', [AdminRolePermissionController::class, 'apiData'])->name('api.admin.roles_permissions');
        Route::post('/api/admin/roles/{role}/permissions', [AdminRolePermissionController::class, 'syncRolePermissions'])->name('api.admin.roles.sync_permissions');
        Route::post('/api/admin/roles', [AdminRolePermissionController::class, 'createRole'])->name('api.admin.roles.create');
        Route::post('/api/admin/permissions', [AdminRolePermissionController::class, 'createPermission'])->name('api.admin.permissions.create');
        Route::delete('/api/admin/permissions/{permission}', [AdminRolePermissionController::class, 'deletePermission'])->name('api.admin.permissions.delete');
        Route::post('/api/admin/users/{user}/assign-role', [AdminRolePermissionController::class, 'assignUserRole'])->name('api.admin.users.assign_role');
        Route::post('/api/admin/roles-permissions/reset-cache', [AdminRolePermissionController::class, 'resetCache'])->name('api.admin.roles.reset_cache');

        // Global Email Suppression & Compliance Management
        Route::get('/admin/email-suppressions', [EmailSuppressionAdminController::class, 'index'])->name('admin.suppressions.index');
        Route::post('/admin/email-suppressions', [EmailSuppressionAdminController::class, 'store'])->name('admin.suppressions.store');
        Route::post('/admin/email-suppressions/{id}/resubscribe', [EmailSuppressionAdminController::class, 'resubscribe'])->name('admin.suppressions.resubscribe');
        Route::get('/admin/email-suppressions/export', [EmailSuppressionAdminController::class, 'export'])->name('admin.suppressions.export');
        Route::get('/admin/email-suppressions/import', [EmailSuppressionImportController::class, 'showImportForm'])->name('admin.suppressions.import');
        Route::post('/admin/email-suppressions/import', [EmailSuppressionImportController::class, 'importCsv'])->name('admin.suppressions.import.post');
        Route::get('/admin/email-suppressions/sample-csv', [EmailSuppressionImportController::class, 'downloadSample'])->name('admin.suppressions.sample');
    });

    // --- AUTHENTICATED JSON API ENDPOINTS ---
    // Dashboard APIs
    Route::get('/api/dashboard/stats', [DashboardController::class, 'apiStats'])->name('api.dashboard.stats');
    Route::get('/api/dashboard/recipient-list', [DashboardController::class, 'apiRecipientList'])->name('api.dashboard.recipient_list');

    // Salesforce Leads & Connection APIs (JSforce Integration)
    Route::get('/api/salesforce/leads', [SalesforceLeadController::class, 'apiLeads'])->name('api.salesforce.leads');
    Route::get('/api/salesforce/test-connection', [SalesforceLeadController::class, 'apiTestConnection'])->name('api.salesforce.test_connection');

    // Salesforce & Campaign Compose APIs
    Route::get('/api/salesforce/recipients', [CampaignController::class, 'apiRecipients'])->name('api.salesforce.recipients');
    Route::get('/api/salesforce/campaign-recipients', [CampaignController::class, 'apiRecipients'])->name('api.salesforce.campaign_recipients');

    // Bulk Mail APIs (Protected by Spatie Bulk-Mail permissions)
    Route::middleware(['permission:bulk-mail|bulk-mail.create|campaign.new|campaign.create|/campaign/new'])->group(function () {
        Route::post('/api/campaign/validate', [CampaignController::class, 'apiValidate'])->name('api.campaign.validate');
    });
    Route::middleware(['permission:bulk-mail|bulk-mail.send'])->group(function () {
        Route::post('/api/campaign/send', [CampaignController::class, 'apiSend'])->name('api.campaign.send');
    });

    Route::get('/api/campaign/{campaign_id}', [CampaignController::class, 'apiDetail'])->name('api.campaign.detail');
    Route::get('/api/campaigns/dropdown', [CampaignController::class, 'apiDropdown'])->name('api.campaigns.dropdown');
    Route::post('/api/campaign/approve', [CampaignController::class, 'apiApprove'])->name('api.campaign.approve');

    // Inbound Replies API
    Route::get('/api/replies', [RepliesController::class, 'apiReplies'])->name('api.replies');

    // Templates, Signatures, and User Assigned Settings APIs
    Route::match(['get', 'post'], '/api/signatures', [TemplateSignatureController::class, 'apiSignatures'])->name('api.signatures');
    Route::delete('/api/signatures/{sig_id}', [TemplateSignatureController::class, 'apiDeleteSignature'])->name('api.signatures.delete');
    Route::match(['get', 'post'], '/api/templates', [TemplateSignatureController::class, 'apiTemplates'])->name('api.templates');
    Route::get('/api/templates/{tmpl_id}', [TemplateSignatureController::class, 'apiGetTemplate'])->name('api.templates.show');
    Route::put('/api/templates/{tmpl_id}', [TemplateSignatureController::class, 'apiUpdateTemplate'])->name('api.templates.update');
    Route::delete('/api/templates/{tmpl_id}', [TemplateSignatureController::class, 'apiDeleteTemplate'])->name('api.templates.delete');
    Route::get('/api/campaign/merge-fields', [TemplateSignatureController::class, 'apiMergeFields'])->name('api.campaign.merge_fields');
    Route::post('/api/campaign/preview', [TemplateSignatureController::class, 'apiPreview'])->name('api.campaign.preview');
    Route::get('/api/user/assigned-settings', [TemplateSignatureController::class, 'apiUserAssignedSettings'])->name('api.user.assigned_settings');

    // Manager APIs
    Route::middleware(['app_auth:admin,manager'])->group(function () {
        Route::get('/api/manager/campaigns', [ManagerController::class, 'apiCampaigns'])->name('api.manager.campaigns');
        Route::get('/api/manager/team-members', [ManagerController::class, 'apiTeamMembers'])->name('api.manager.team_members');
    });

    // Admin APIs
    Route::middleware(['app_auth:admin'])->group(function () {
        Route::match(['get', 'post', 'put', 'delete'], '/api/admin/users', [AdminController::class, 'apiUsers'])->name('api.admin.users');
        Route::get('/api/admin/managers', [AdminController::class, 'apiManagers'])->name('api.admin.managers');
        Route::get('/api/admin/audit-logs', [AdminController::class, 'apiAuditLogs'])->name('api.admin.audit_logs');
        Route::match(['get', 'post', 'put', 'delete'], '/api/admin/servers', [AdminController::class, 'apiServers'])->name('api.admin.servers');
        Route::match(['get', 'post', 'put', 'delete'], '/api/admin/domains', [AdminController::class, 'apiDomains'])->name('api.admin.domains');
        Route::match(['get', 'post'], '/api/admin/crm-settings', [AdminController::class, 'apiCrmSettings'])->name('api.admin.crm_settings');
    });

    // Simulator APIs
    Route::match(['get', 'post'], '/api/simulator/salesforce-records', [SimulatorController::class, 'apiSalesforceRecords'])->name('api.simulator.salesforce_records');
    Route::post('/api/simulator/update-salesforce', [SimulatorController::class, 'apiUpdateSalesforce'])->name('api.simulator.update_salesforce');
    Route::post('/api/simulator/inbound-reply', [SimulatorController::class, 'apiInboundReply'])->name('api.simulator.inbound_reply');
    Route::post('/api/simulator/clear-cache', [SimulatorController::class, 'apiClearCache'])->name('api.simulator.clear_cache');
});


Route::get('/salesforce-test', function () {

    // =====================================================
    // SALESFORCE CONFIGURATION
    // =====================================================

    $sfLoginUrl = 'https://login.salesforce.com';

    $sfUsername = 'pramod@retrotech.in';

    // Salesforce password + security token
    $sfPassword = 'Admin@Retrotech#2033HctxqZXWac6krSA8CWPVwACjF';

    $apiVersion = '61.0';

    $soapUrl = $sfLoginUrl . '/services/Soap/u/' . $apiVersion;


    // =====================================================
    // CREATE SOAP REQUEST
    // =====================================================

    $soapRequest = '<?xml version="1.0" encoding="utf-8" ?>'
        . '<env:Envelope xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
        . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
        . ' xmlns:env="http://schemas.xmlsoap.org/soap/envelope/">'
        . '<env:Body>'
        . '<n1:login xmlns:n1="urn:partner.soap.sforce.com">'
        . '<n1:username>'
        . htmlspecialchars($sfUsername, ENT_XML1)
        . '</n1:username>'
        . '<n1:password>'
        . htmlspecialchars($sfPassword, ENT_XML1)
        . '</n1:password>'
        . '</n1:login>'
        . '</env:Body>'
        . '</env:Envelope>';


    try {

        // =====================================================
        // SEND REQUEST TO SALESFORCE
        // =====================================================

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=UTF-8',
            'SOAPAction'   => 'login',
        ])
        ->timeout(30)
        ->withBody($soapRequest, 'text/xml')
        ->post($soapUrl);


        $httpCode = $response->status();

        $responseBody = $response->body();


        // =====================================================
        // CHECK SALESFORCE CONNECTION
        // =====================================================

        if (
            $response->successful() &&
            strpos($responseBody, '<sessionId>') !== false
        ) {

            // Get Session ID
            preg_match(
                '/<sessionId>(.*?)<\/sessionId>/',
                $responseBody,
                $sessionMatch
            );

            // Get Salesforce Server URL
            preg_match(
                '/<serverUrl>(.*?)<\/serverUrl>/',
                $responseBody,
                $serverMatch
            );

            $sessionId = $sessionMatch[1] ?? '';

            $serverUrl = $serverMatch[1] ?? '';


            // Hide most of session ID
            $maskedSessionId = substr($sessionId, 0, 20)
                . '********************';


            // =====================================================
            // SUCCESS PAGE
            // =====================================================

            return response('
                <!DOCTYPE html>

                <html>

                <head>

                    <title>Salesforce Connection Test</title>

                    <style>

                        body {
                            font-family: Arial, sans-serif;
                            background: #f5f7fa;
                            padding: 40px;
                        }

                        .container {
                            max-width: 900px;
                            margin: auto;
                            background: #ffffff;
                            padding: 30px;
                            border-radius: 10px;
                            box-shadow: 0 4px 15px rgba(0,0,0,.1);
                        }

                        .success {
                            background: #d4edda;
                            color: #155724;
                            padding: 20px;
                            border-radius: 6px;
                        }

                    </style>

                </head>

                <body>

                    <div class="container">

                        <h2>Salesforce Connection Test</h2>

                        <div class="success">

                            <h3>
                                ✅ Salesforce Connected Successfully!
                            </h3>

                            <p>
                                <strong>Username:</strong>
                                ' . e($sfUsername) . '
                            </p>

                            <p>
                                <strong>HTTP Status:</strong>
                                ' . e($httpCode) . '
                            </p>

                            <p>
                                <strong>Salesforce Server:</strong>
                                <br>
                                ' . e($serverUrl) . '
                            </p>

                            <p>
                                <strong>Session ID:</strong>
                                <br>
                                ' . e($maskedSessionId) . '
                            </p>

                        </div>

                    </div>

                </body>

                </html>
            ');

        }


        // =====================================================
        // SALESFORCE LOGIN FAILED
        // =====================================================

        return response('

            <!DOCTYPE html>

            <html>

            <head>

                <title>Salesforce Connection Failed</title>

                <style>

                    body {
                        font-family: Arial, sans-serif;
                        background: #f5f7fa;
                        padding: 40px;
                    }

                    .container {
                        max-width: 900px;
                        margin: auto;
                        background: white;
                        padding: 30px;
                        border-radius: 10px;
                    }

                    .error {
                        background: #f8d7da;
                        color: #721c24;
                        padding: 20px;
                        border-radius: 6px;
                    }

                    pre {
                        background: #222;
                        color: #eee;
                        padding: 20px;
                        overflow: auto;
                        border-radius: 6px;
                    }

                </style>

            </head>

            <body>

                <div class="container">

                    <h2>Salesforce Connection Test</h2>

                    <div class="error">

                        <h3>
                            ❌ Salesforce Connection Failed
                        </h3>

                        <p>
                            <strong>HTTP Status:</strong>
                            ' . e($httpCode) . '
                        </p>

                    </div>

                    <h3>Salesforce Response</h3>

                    <pre>'
                        . e($responseBody)
                    . '</pre>

                </div>

            </body>

            </html>

        ');

    } catch (\Exception $e) {

        return response('
            <h2>Salesforce Connection Error</h2>

            <p style="
                background:#f8d7da;
                color:#721c24;
                padding:20px;
            ">
                ' . e($e->getMessage()) . '
            </p>
        ', 500);
    }

});
