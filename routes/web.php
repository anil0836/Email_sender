<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\RepliesController;
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

// --- AUTHENTICATED WEB UI ROUTES ---
Route::middleware(['app_auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard_view');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/campaign/new', [CampaignController::class, 'createView'])->name('campaign_create_view');
    Route::get('/campaign/create', [CampaignController::class, 'createView']);
    Route::get('/campaign/{campaign_id}', [CampaignController::class, 'detailView'])->name('campaign_detail_view');
    Route::get('/replies', [RepliesController::class, 'index'])->name('replies_view');
    Route::get('/simulator', [SimulatorController::class, 'index'])->name('simulator_view');
    Route::get('/download/attachment/{campaign_id}/{filename}', [CampaignController::class, 'downloadAttachment'])->name('download_attachment');

    // Manager and Admin UI
    Route::middleware(['app_auth:admin,manager'])->group(function () {
        Route::get('/manager/campaigns', [ManagerController::class, 'campaignsView'])->name('team_campaigns_view');
    });

    // Admin-only UI
    Route::middleware(['app_auth:admin'])->group(function () {
        Route::get('/admin/users', [AdminController::class, 'usersView'])->name('admin_users_view');
        Route::get('/admin/infrastructure', [AdminController::class, 'infrastructureView'])->name('admin_infrastructure_view');
    });

    // --- AUTHENTICATED JSON API ENDPOINTS ---
    // Dashboard APIs
    Route::get('/api/dashboard/stats', [DashboardController::class, 'apiStats'])->name('api.dashboard.stats');
    Route::get('/api/dashboard/recipient-list', [DashboardController::class, 'apiRecipientList'])->name('api.dashboard.recipient_list');

    // Salesforce & Campaign Compose APIs
    Route::get('/api/salesforce/recipients', [SimulatorController::class, 'apiSalesforceRecipients'])->name('api.salesforce.recipients');
    Route::post('/api/campaign/validate', [CampaignController::class, 'apiValidate'])->name('api.campaign.validate');
    Route::post('/api/campaign/send', [CampaignController::class, 'apiSend'])->name('api.campaign.send');
    Route::get('/api/campaign/{campaign_id}', [CampaignController::class, 'apiDetail'])->name('api.campaign.detail');
    Route::get('/api/campaigns/dropdown', [CampaignController::class, 'apiDropdown'])->name('api.campaigns.dropdown');
    Route::post('/api/campaign/approve', [CampaignController::class, 'apiApprove'])->name('api.campaign.approve');

    // Inbound Replies API
    Route::get('/api/replies', [RepliesController::class, 'apiReplies'])->name('api.replies');

    // Templates, Signatures, and User Assigned Settings APIs
    Route::match(['get', 'post'], '/api/signatures', [TemplateSignatureController::class, 'apiSignatures'])->name('api.signatures');
    Route::delete('/api/signatures/{sig_id}', [TemplateSignatureController::class, 'apiDeleteSignature'])->name('api.signatures.delete');
    Route::match(['get', 'post'], '/api/templates', [TemplateSignatureController::class, 'apiTemplates'])->name('api.templates');
    Route::delete('/api/templates/{tmpl_id}', [TemplateSignatureController::class, 'apiDeleteTemplate'])->name('api.templates.delete');
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
