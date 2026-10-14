<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\SalesforceLead;
use App\Models\SalesforceContact;
use App\Services\SalesforceOwnerService;
use App\Services\SalesforceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

echo "=======================================================\n";
echo " SALESFORCE LEAD & CONTACT USER-BASED FILTERING TEST\n";
echo "=======================================================\n\n";

$ownerService = app(SalesforceOwnerService::class);
$sfService = app(SalesforceService::class);

$allPassed = true;

function assertCondition(bool $condition, string $description) {
    global $allPassed;
    if ($condition) {
        echo " [PASS] " . $description . "\n";
    } else {
        echo " [FAIL] " . $description . "\n";
        $allPassed = false;
    }
}

// -------------------------------------------------------------
// 1. Authenticated User: Ava
// -------------------------------------------------------------
echo "\n--- Scenario A: Ava logs in ---\n";
$ava = User::where('username', 'ava')->first();
assertCondition($ava !== null, "Found user Ava in database");

$avaLeads = SalesforceLead::forUser($ava)->get();
$avaContacts = SalesforceContact::forUser($ava)->get();

assertCondition($avaLeads->count() > 0, "Ava can view her own Leads (Count: {$avaLeads->count()})");
assertCondition($avaContacts->count() > 0, "Ava can view her own Contacts (Count: {$avaContacts->count()})");

// Verify no Hazel leads appear for Ava
$hazelLeadsUnderAva = $avaLeads->filter(function ($l) {
    return strtolower($l->owner_name ?? '') === 'hazel' || strtolower($l->Custom_Owner__c ?? '') === 'hazel';
});
assertCondition($hazelLeadsUnderAva->count() === 0, "Hazel Leads are completely HIDDEN from Ava (Count: {$hazelLeadsUnderAva->count()})");

// Verify no Hazel contacts appear for Ava
$hazelContactsUnderAva = $avaContacts->filter(function ($c) {
    return strtolower($c->owner_name ?? '') === 'hazel' || strtolower($c->Custom_Owner__c ?? '') === 'hazel';
});
assertCondition($hazelContactsUnderAva->count() === 0, "Hazel Contacts are completely HIDDEN from Ava (Count: {$hazelContactsUnderAva->count()})");

// -------------------------------------------------------------
// 2. Authenticated User: Hazel
// -------------------------------------------------------------
echo "\n--- Scenario B: Hazel logs in ---\n";
$hazel = User::where('username', 'hazel')->first();
assertCondition($hazel !== null, "Found user Hazel in database");

$hazelLeads = SalesforceLead::forUser($hazel)->get();
$hazelContacts = SalesforceContact::forUser($hazel)->get();

assertCondition($hazelLeads->count() > 0, "Hazel can view her own Leads (Count: {$hazelLeads->count()})");
assertCondition($hazelContacts->count() > 0, "Hazel can view her own Contacts (Count: {$hazelContacts->count()})");

// Verify no Ava leads appear for Hazel
$avaLeadsUnderHazel = $hazelLeads->filter(function ($l) {
    return strtolower($l->owner_name ?? '') === 'ava' || strtolower($l->Custom_Owner__c ?? '') === 'ava';
});
assertCondition($avaLeadsUnderHazel->count() === 0, "Ava Leads are completely HIDDEN from Hazel (Count: {$avaLeadsUnderHazel->count()})");

// Verify no Ava contacts appear for Hazel
$avaContactsUnderHazel = $hazelContacts->filter(function ($c) {
    return strtolower($c->owner_name ?? '') === 'ava' || strtolower($c->Custom_Owner__c ?? '') === 'ava';
});
assertCondition($avaContactsUnderHazel->count() === 0, "Ava Contacts are completely HIDDEN from Hazel (Count: {$avaContactsUnderHazel->count()})");

// -------------------------------------------------------------
// 3. Authenticated User: Another User (Ben)
// -------------------------------------------------------------
echo "\n--- Scenario C: Another user logs in (Ben) ---\n";
$ben = User::where('username', 'ben')->first();
assertCondition($ben !== null, "Found user Ben in database");

$benLeads = SalesforceLead::forUser($ben)->get();
$benContacts = SalesforceContact::forUser($ben)->get();

assertCondition($benLeads->count() > 0, "Ben can view his own Leads (Count: {$benLeads->count()})");
assertCondition($benContacts->count() > 0, "Ben can view his own Contacts (Count: {$benContacts->count()})");

// Verify neither Ava nor Hazel leads appear for Ben
$otherLeadsUnderBen = $benLeads->filter(function ($l) {
    $owner = strtolower($l->owner_name ?? ($l->Custom_Owner__c ?? ''));
    return $owner === 'ava' || $owner === 'hazel';
});
assertCondition($otherLeadsUnderBen->count() === 0, "Neither Ava nor Hazel Leads appear for Ben (Count: {$otherLeadsUnderBen->count()})");

// -------------------------------------------------------------
// 4. Authenticated User with no records
// -------------------------------------------------------------
echo "\n--- Scenario D: User with no assigned records ---\n";
$steven = User::where('username', 'steven')->first();
if ($steven) {
    $stevenLeads = SalesforceLead::forUser($steven)->get();
    $stevenContacts = SalesforceContact::forUser($steven)->get();
    assertCondition($stevenLeads->count() === 0, "User Steven sees 0 leads (proper empty state)");
    assertCondition($stevenContacts->count() === 0, "User Steven sees 0 contacts (proper empty state)");
}

// -------------------------------------------------------------
// 5. Unauthenticated User (Access Denied / 0 Records)
// -------------------------------------------------------------
echo "\n--- Scenario E: Unauthenticated Access ---\n";
$unauthLeads = SalesforceLead::forUser(null)->get();
$unauthContacts = SalesforceContact::forUser(null)->get();
assertCondition($unauthLeads->count() === 0, "Unauthenticated user gets 0 Leads");
assertCondition($unauthContacts->count() === 0, "Unauthenticated user gets 0 Contacts");

// -------------------------------------------------------------
// 6. CampaignController::apiRecipients Validation
// -------------------------------------------------------------
echo "\n--- Scenario F: CampaignController::apiRecipients ---\n";
$campaignController = app(\App\Http\Controllers\CampaignController::class);

// Call apiRecipients as Ava
Auth::setUser($ava);
$reqAva = Request::create('/api/salesforce/recipients', 'GET', ['type' => 'all', 'limit' => 5000]);
$resAva = $campaignController->apiRecipients($reqAva);
$avaRecipients = json_decode($resAva->getContent(), true);

$hazelUnderAvaRecipients = array_filter($avaRecipients, function ($r) {
    return strtolower($r['owner_name'] ?? '') === 'hazel' || strtolower($r['custom_owner'] ?? '') === 'hazel';
});
assertCondition(count($avaRecipients) > 0, "Ava apiRecipients returns records (Total: " . count($avaRecipients) . ")");
assertCondition(count($hazelUnderAvaRecipients) === 0, "Ava apiRecipients returns ZERO Hazel records (Count: " . count($hazelUnderAvaRecipients) . ")");

// Call apiRecipients as Hazel
Auth::setUser($hazel);
$reqHazel = Request::create('/api/salesforce/recipients', 'GET', ['type' => 'all', 'limit' => 5000]);
$resHazel = $campaignController->apiRecipients($reqHazel);
$hazelRecipients = json_decode($resHazel->getContent(), true);

$avaUnderHazelRecipients = array_filter($hazelRecipients, function ($r) {
    return strtolower($r['owner_name'] ?? '') === 'ava' || strtolower($r['custom_owner'] ?? '') === 'ava';
});
assertCondition(count($hazelRecipients) > 0, "Hazel apiRecipients returns records (Total: " . count($hazelRecipients) . ")");
assertCondition(count($avaUnderHazelRecipients) === 0, "Hazel apiRecipients returns ZERO Ava records (Count: " . count($avaUnderHazelRecipients) . ")");

// Call apiRecipients as Unauthenticated
Auth::logout();
$reqUnauth = Request::create('/api/salesforce/recipients', 'GET', ['type' => 'all']);
$resUnauth = $campaignController->apiRecipients($reqUnauth);
assertCondition($resUnauth->getStatusCode() === 401, "Unauthenticated call to apiRecipients returns 401 Unauthenticated");

// -------------------------------------------------------------
// 7. Cross-Owner Direct Access / URL Tampering Prevention
// -------------------------------------------------------------
echo "\n--- Scenario G: Direct Record Access & Validation Enforcement ---\n";
$hazelLeadSample = $hazelLeads->first();
if ($hazelLeadSample) {
    // Check if Ava can validate or send to Hazel's lead
    [$isEligible, $reason, $rec] = $sfService->checkRecipientEligibility($hazelLeadSample->salesforce_id, 'ava');
    assertCondition(!$isEligible && $reason === 'DIFFERENT_OWNER', "Ava attempting to send to Hazel's lead returns DIFFERENT_OWNER (Denied)");

    // Check if Hazel can validate her own lead
    [$isEligibleHazel, $reasonHazel, $recHazel] = $sfService->checkRecipientEligibility($hazelLeadSample->salesforce_id, 'hazel');
    assertCondition($reasonHazel !== 'DIFFERENT_OWNER', "Hazel validating her own lead does not return DIFFERENT_OWNER");

    // Check admin show method direct access prevention
    $adminController = app(\App\Http\Controllers\Admin\SalesforceLeadAdminController::class);
    $reqAvaDirect = Request::create('/admin/salesforce-leads/' . $hazelLeadSample->id, 'GET');
    $reqAvaDirect->setUserResolver(fn() => $ava);
    
    $blocked = false;
    try {
        $adminController->show($reqAvaDirect, $hazelLeadSample->id);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        if ($e->getStatusCode() === 404) {
            $blocked = true;
        }
    }
    assertCondition($blocked, "Ava directly requesting Hazel's Lead ID in Admin show() is denied (404 Not Found)");
}

// -------------------------------------------------------------
// 8. Live Salesforce Query Script Owner Parameter Test
// -------------------------------------------------------------
echo "\n--- Scenario H: Live Salesforce Lead Service Owner Scoping ---\n";
$leadService = app(\App\Services\SalesforceLeadService::class);
$unauthResult = $leadService->getLeads(25, 0, '', null, null);
assertCondition($unauthResult['success'] === true && $unauthResult['total'] === 0 && empty($unauthResult['records']), "SalesforceLeadService with no owner returns 0 records safely");

echo "\n=======================================================\n";
if ($allPassed) {
    echo " ALL TESTS PASSED SUCCESSFULLY! \n";
} else {
    echo " SOME TESTS FAILED. PLEASE REVIEW ABOVE LOGS. \n";
}
echo "=======================================================\n";
