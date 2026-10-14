<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$leadService = app(\App\Services\SalesforceLeadService::class);
$lp = $leadService->getLeadPicklists();
$cp = $leadService->getContactPicklists();

$leadCats = array_map(fn($v) => $v['label'], $lp['fields']['Deal_Category__c']['values'] ?? []);
$contactCats = array_map(fn($v) => $v['label'], $cp['fields']['Deal_Category__c']['values'] ?? []);

echo "Lead Categories matching 'audio':\n";
foreach ($leadCats as $c) {
    if (stripos($c, 'audio') !== false) {
        echo " - " . $c . "\n";
    }
}

echo "\nContact Categories matching 'audio':\n";
foreach ($contactCats as $c) {
    if (stripos($c, 'audio') !== false) {
        echo " - " . $c . "\n";
    }
}

echo "\nLead Regions matching 'asia':\n";
foreach (array_map(fn($v) => $v['label'], $lp['fields']['Region__c']['values'] ?? []) as $r) {
    if (stripos($r, 'asia') !== false) {
        echo " - " . $r . "\n";
    }
}

echo "\nLead Countries matching 'mex':\n";
foreach (array_map(fn($v) => $v['label'], $lp['fields']['Country_Custom__c']['values'] ?? []) as $c) {
    if (stripos($c, 'mex') !== false) {
        echo " - " . $c . "\n";
    }
}
