<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('email:process-queue')->everyMinute();

// Salesforce Leads Synchronization (every 15 minutes, non-overlapping)
Schedule::command('salesforce:sync-leads')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Salesforce Contacts Synchronization (every 30 minutes, non-overlapping)
Schedule::command('salesforce:sync-contacts')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Salesforce Accounts Synchronization (hourly, non-overlapping)
Schedule::command('salesforce:sync-accounts')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Salesforce Users Synchronization (daily at 02:00, non-overlapping)
Schedule::command('salesforce:sync-users')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Salesforce Custom Users (SF_User__c) Synchronization (daily at 02:30, non-overlapping)
Schedule::command('salesforce:sync-sf-users')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground();
