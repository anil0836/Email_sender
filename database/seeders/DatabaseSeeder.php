<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CrmSetting;
use App\Models\GlobalSuppression;
use App\Models\InboundReply;
use App\Models\RecipientClick;
use App\Models\RecipientLog;
use App\Models\RecipientOpen;
use App\Models\SalesforceMockRecord;
use App\Models\SendingDomain;
use App\Models\Server;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Users
        if (User::count() === 0) {
            $admin = User::create([
                'emp_id' => 'EMP-001',
                'username' => 'admin',
                'name' => 'Admin User',
                'email' => 'admin@b2bbulkmail.com',
                'password' => Hash::make('admin'),
                'role' => 'admin',
                'daily_limit' => 5000,
            ]);

            $manager = User::create([
                'emp_id' => 'EMP-002',
                'username' => 'manager',
                'name' => 'Sales Manager',
                'email' => 'manager@b2bbulkmail.com',
                'password' => Hash::make('manager'),
                'role' => 'manager',
                'daily_limit' => 3000,
            ]);

            User::create([
                'emp_id' => 'EMP-003',
                'username' => 'user',
                'name' => 'Standard Sender',
                'email' => 'user@b2bbulkmail.com',
                'password' => Hash::make('user'),
                'role' => 'user',
                'manager_id' => $manager->id,
                'daily_limit' => 1000,
            ]);
        }

        // 2. Servers
        if (Server::count() === 0) {
            Server::create([
                'name' => 'Primary SMTP Server',
                'host' => 'smtp.sendgrid.net',
                'port' => 587,
                'username' => 'apikey',
                'password' => 'SG.mock_key',
                'sending_ip' => '192.168.1.50',
                'is_active' => true,
            ]);

            Server::create([
                'name' => 'Backup Mail Server',
                'host' => 'smtp.mailgun.org',
                'port' => 587,
                'username' => 'postmaster',
                'password' => 'mg.mock_key',
                'sending_ip' => '192.168.1.51',
                'is_active' => true,
            ]);

            Server::create([
                'name' => 'Legacy IP Server',
                'host' => 'mail.internal.net',
                'port' => 25,
                'username' => null,
                'password' => null,
                'sending_ip' => '10.0.0.12',
                'is_active' => false,
            ]);
        }

        // 3. Sending Domains
        if (SendingDomain::count() === 0) {
            SendingDomain::create([
                'domain_name' => 'marketing.example.com',
                'status' => 'enabled',
                'spf_status' => 'verified',
                'dkim_status' => 'verified',
                'dmarc_status' => 'verified',
                'server_id' => 1,
                'is_default' => true,
                'rate_limit_per_hour' => 5000,
            ]);

            SendingDomain::create([
                'domain_name' => 'sales.example.com',
                'status' => 'enabled',
                'spf_status' => 'verified',
                'dkim_status' => 'verified',
                'dmarc_status' => 'unverified',
                'server_id' => 1,
                'is_default' => false,
                'rate_limit_per_hour' => 2000,
            ]);

            SendingDomain::create([
                'domain_name' => 'offers.example.net',
                'status' => 'enabled',
                'spf_status' => 'unverified',
                'dkim_status' => 'unverified',
                'dmarc_status' => 'unverified',
                'server_id' => 2,
                'is_default' => false,
                'rate_limit_per_hour' => 1000,
            ]);
        }

        // 4. Global Suppression
        if (GlobalSuppression::count() === 0) {
            GlobalSuppression::create(['email' => 'spammer-trap@aol.com', 'reason' => 'Spam Trap']);
            GlobalSuppression::create(['email' => 'unsubscribed-lead@gmail.com', 'reason' => 'Previous Unsubscribe']);
            GlobalSuppression::create(['email' => 'hard-bounce-customer@yahoo.com', 'reason' => 'Hard Bounce']);
        }

        // 5. CRM Settings
        if (CrmSetting::count() === 0) {
            CrmSetting::create([
                'salesforce_client_id' => '3MVG9qN...',
                'salesforce_client_secret' => '7A8B9C...',
                'salesforce_login_url' => 'https://login.salesforce.com',
                'salesforce_username' => 'api-user@b2bbulkmail.com',
                'salesforce_token_or_password' => 'passwordSecurityToken12345',
                'is_mock' => true,
            ]);
        }

        // 6. Salesforce Mock Records
        if (SalesforceMockRecord::count() === 0) {
            $sfRecords = [
                // Contacts owned by Admin
                ['id' => '003SF0000000001', 'object_type' => 'Contact', 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john.doe@example.com', 'owner_id' => 'admin', 'opted_out' => false, 'status' => 'Active', 'consent_status' => 'valid', 'lawful_basis' => 'Legitimate Interest', 'do_not_call' => false, 'deal_category' => 'Mac', 'region' => 'North America', 'country' => 'USA'],
                ['id' => '003SF0000000002', 'object_type' => 'Contact', 'first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane.smith@example.com', 'owner_id' => 'admin', 'opted_out' => false, 'status' => 'Active', 'consent_status' => 'valid', 'lawful_basis' => 'Legitimate Interest', 'do_not_call' => false, 'deal_category' => 'Laptops', 'region' => 'North America', 'country' => 'Canada'],
                ['id' => '003SF0000000003', 'object_type' => 'Contact', 'first_name' => 'Robert', 'last_name' => 'Johnson', 'email' => 'robert.johnson@example.com', 'owner_id' => 'admin', 'opted_out' => true, 'status' => 'Active', 'consent_status' => 'valid', 'lawful_basis' => 'Legitimate Interest', 'do_not_call' => false, 'deal_category' => 'Desktop', 'region' => 'EMEA', 'country' => 'United Kingdom'], // Opted out
                ['id' => '003SF0000000004', 'object_type' => 'Contact', 'first_name' => 'Emily', 'last_name' => 'Williams', 'email' => 'emily.williams@example.com', 'owner_id' => 'admin', 'opted_out' => false, 'status' => 'Inactive', 'consent_status' => 'valid', 'lawful_basis' => 'Legitimate Interest', 'do_not_call' => false, 'deal_category' => 'Iphone', 'region' => 'EMEA', 'country' => 'Germany'], // Inactive
                ['id' => '003SF0000000005', 'object_type' => 'Contact', 'first_name' => 'Michael', 'last_name' => 'Brown', 'email' => 'michael.brown@example.com', 'owner_id' => 'admin', 'opted_out' => false, 'status' => 'Active', 'consent_status' => 'missing', 'lawful_basis' => 'None', 'do_not_call' => false, 'deal_category' => 'Ipad', 'region' => 'APAC', 'country' => 'India'], // Missing consent

                // Contacts owned by User
                ['id' => '003SF0000000006', 'object_type' => 'Contact', 'first_name' => 'David', 'last_name' => 'Jones', 'email' => 'david.jones@example.com', 'owner_id' => 'user', 'opted_out' => false, 'status' => 'Active', 'consent_status' => 'valid', 'lawful_basis' => 'Consent', 'do_not_call' => false, 'deal_category' => 'Imac', 'region' => 'APAC', 'country' => 'Australia'],
                ['id' => '003SF0000000007', 'object_type' => 'Contact', 'first_name' => 'Sarah', 'last_name' => 'Miller', 'email' => 'sarah.miller@example.com', 'owner_id' => 'user', 'opted_out' => false, 'status' => 'Active', 'consent_status' => 'valid', 'lawful_basis' => 'Consent', 'do_not_call' => false, 'deal_category' => 'Mac', 'region' => 'APAC', 'country' => 'Japan'],
                ['id' => '003SF0000000008', 'object_type' => 'Contact', 'first_name' => 'James', 'last_name' => 'Davis', 'email' => 'james.davis@example.com', 'owner_id' => 'user', 'opted_out' => true, 'status' => 'Active', 'consent_status' => 'valid', 'lawful_basis' => 'Consent', 'do_not_call' => false, 'deal_category' => 'Laptops', 'region' => 'LATAM', 'country' => 'Brazil'], // Opted out
                ['id' => '003SF0000000009', 'object_type' => 'Contact', 'first_name' => 'Karen', 'last_name' => 'Garcia', 'email' => 'karen.garcia@example.com', 'owner_id' => 'user', 'opted_out' => false, 'status' => 'Inactive', 'consent_status' => 'valid', 'lawful_basis' => 'Consent', 'do_not_call' => false, 'deal_category' => 'Desktop', 'region' => 'North America', 'country' => 'USA'], // Inactive
                ['id' => '003SF0000000010', 'object_type' => 'Contact', 'first_name' => 'Joseph', 'last_name' => 'Rodriguez', 'email' => 'unsubscribed-lead@gmail.com', 'owner_id' => 'user', 'opted_out' => false, 'status' => 'Active', 'consent_status' => 'valid', 'lawful_basis' => 'Consent', 'do_not_call' => false, 'deal_category' => 'Iphone', 'region' => 'North America', 'country' => 'USA'], // Globally suppressed

                // Leads owned by Admin
                ['id' => '00QSF0000000001', 'object_type' => 'Lead', 'first_name' => 'William', 'last_name' => 'Wilson', 'email' => 'william.wilson@example.com', 'owner_id' => 'admin', 'opted_out' => false, 'status' => 'New', 'consent_status' => 'valid', 'lawful_basis' => 'Legitimate Interest', 'do_not_call' => false, 'deal_category' => 'Ipad', 'region' => 'EMEA', 'country' => 'United Kingdom'],
                ['id' => '00QSF0000000002', 'object_type' => 'Lead', 'first_name' => 'Elizabeth', 'last_name' => 'Thomas', 'email' => 'elizabeth.thomas@example.com', 'owner_id' => 'admin', 'opted_out' => false, 'status' => 'Nurturing', 'consent_status' => 'valid', 'lawful_basis' => 'Legitimate Interest', 'do_not_call' => false, 'deal_category' => 'Imac', 'region' => 'EMEA', 'country' => 'Germany'],
                ['id' => '00QSF0000000003', 'object_type' => 'Lead', 'first_name' => 'Charles', 'last_name' => 'Taylor', 'email' => 'charles.taylor@example.com', 'owner_id' => 'admin', 'opted_out' => false, 'status' => 'Disqualified', 'consent_status' => 'valid', 'lawful_basis' => 'Legitimate Interest', 'do_not_call' => false, 'deal_category' => 'Mac', 'region' => 'APAC', 'country' => 'India'], // Disqualified

                // Leads owned by User
                ['id' => '00QSF0000000004', 'object_type' => 'Lead', 'first_name' => 'Thomas', 'last_name' => 'Moore', 'email' => 'thomas.moore@example.com', 'owner_id' => 'user', 'opted_out' => false, 'status' => 'New', 'consent_status' => 'valid', 'lawful_basis' => 'Consent', 'do_not_call' => false, 'deal_category' => 'Laptops', 'region' => 'APAC', 'country' => 'Australia'],
                ['id' => '00QSF0000000005', 'object_type' => 'Lead', 'first_name' => 'Margaret', 'last_name' => 'Jackson', 'email' => 'margaret.jackson@example.com', 'owner_id' => 'user', 'opted_out' => false, 'status' => 'Working', 'consent_status' => 'valid', 'lawful_basis' => 'Consent', 'do_not_call' => false, 'deal_category' => 'Desktop', 'region' => 'APAC', 'country' => 'Japan'],
                ['id' => '00QSF0000000006', 'object_type' => 'Lead', 'first_name' => 'Christopher', 'last_name' => 'Martin', 'email' => 'christopher.martin@example.com', 'owner_id' => 'user', 'opted_out' => false, 'status' => 'Nurturing', 'consent_status' => 'valid', 'lawful_basis' => 'Consent', 'do_not_call' => false, 'deal_category' => 'Iphone', 'region' => 'LATAM', 'country' => 'Brazil'],
                ['id' => '00QSF0000000007', 'object_type' => 'Lead', 'first_name' => 'Patricia', 'last_name' => 'Lee', 'email' => 'patricia.lee@example.com', 'owner_id' => 'user', 'opted_out' => false, 'status' => 'New', 'consent_status' => 'missing', 'lawful_basis' => 'None', 'do_not_call' => false, 'deal_category' => 'Ipad', 'region' => 'North America', 'country' => 'USA'], // Missing consent
            ];

            $firstNames = ['James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda', 'William', 'Elizabeth'];
            $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
            $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'company.com', 'business.net'];
            $categories = ['Desktop', 'Laptops', 'Mac', 'Ipad', 'Imac', 'Iphone'];
            $regions = ['North America', 'EMEA', 'APAC', 'LATAM'];
            $countriesByRegion = [
                'North America' => ['USA', 'Canada'],
                'EMEA' => ['United Kingdom', 'Germany'],
                'APAC' => ['India', 'Australia', 'Japan'],
                'LATAM' => ['Brazil'],
            ];

            for ($i = 11; $i <= 39; $i++) {
                $sfId = sprintf('003SF00000000%02d', $i);
                $fname = $firstNames[array_rand($firstNames)];
                $lname = $lastNames[array_rand($lastNames)];
                $email = strtolower($fname) . '.' . strtolower($lname) . $i . '@' . $domains[array_rand($domains)];
                $owner = ($i % 2 === 0) ? 'user' : 'admin';
                $optOut = ($i % 7 === 0);
                $status = ($i % 8 !== 0) ? 'Active' : 'Inactive';
                $consent = ($i % 9 !== 0) ? 'valid' : 'missing';
                $basis = ($consent === 'valid') ? 'Consent' : 'None';
                $reg = $regions[array_rand($regions)];
                $cty = $countriesByRegion[$reg][array_rand($countriesByRegion[$reg])];
                $cat = $categories[array_rand($categories)];

                $sfRecords[] = [
                    'id' => $sfId,
                    'object_type' => 'Contact',
                    'first_name' => $fname,
                    'last_name' => $lname,
                    'email' => $email,
                    'owner_id' => $owner,
                    'opted_out' => $optOut,
                    'status' => $status,
                    'consent_status' => $consent,
                    'lawful_basis' => $basis,
                    'do_not_call' => false,
                    'deal_category' => $cat,
                    'region' => $reg,
                    'country' => $cty,
                ];
            }

            foreach ($sfRecords as $rec) {
                SalesforceMockRecord::create($rec);
            }
        }

        // 7. Historical Analytics & Campaigns Seed
        if (Campaign::count() === 0) {
            $adminUser = User::where('username', 'admin')->first();
            $standardUser = User::where('username', 'user')->first();
            $usersMap = ['admin' => $adminUser->id, 'user' => $standardUser->id];

            $geoLocations = [
                ['US', 'California', 'San Francisco', '192.0.2.1'],
                ['US', 'New York', 'New York', '198.51.100.22'],
                ['GB', 'England', 'London', '203.0.113.5'],
                ['IN', 'Maharashtra', 'Mumbai', '103.21.244.15'],
                ['DE', 'Hesse', 'Frankfurt', '46.165.192.1'],
                ['CA', 'Ontario', 'Toronto', '24.114.22.4'],
                ['AU', 'New South Wales', 'Sydney', '1.120.0.0'],
                ['FR', 'Île-de-France', 'Paris', '80.12.0.0'],
                ['JP', 'Tokyo', 'Tokyo', '210.140.0.0'],
                ['BR', 'São Paulo', 'São Paulo', '200.18.0.0'],
            ];

            $subjects = [
                'Q3 Product Update & Feature Launch',
                'Special Summer Offers - Save Up to 40%',
                'Urgent: Security Update for Your Account',
                'Invitation: Annual Tech Developers Meetup 2026',
                'How to maximize your sales pipeline with CRM',
                'Exclusive webinar: AI in modern business operations',
                'Thank you for being our loyal partner!',
                'Weekly Roundup: Top industry insights and articles',
            ];

            $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'company.com', 'business.net'];
            $now = Carbon::now();

            for ($dayOffset = 30; $dayOffset > 0; $dayOffset -= 3) {
                $campaignId = (string) Str::uuid();
                $userKey = ($dayOffset % 2 === 0) ? 'user' : 'admin';
                $userId = $usersMap[$userKey];
                $createdAt = $now->copy()->subDays($dayOffset)->subHours(rand(1, 10));

                $sub = $subjects[array_rand($subjects)];
                $totalReq = rand(15, 35);
                $blockedOwner = rand(1, 4);
                $blockedOptout = rand(0, 2);
                $blockedSupp = rand(0, 1);
                $blockedTotal = $blockedOwner + $blockedOptout + $blockedSupp;
                $approvedTotal = $totalReq - $blockedTotal;

                $campaign = Campaign::create([
                    'id' => $campaignId,
                    'subject' => $sub,
                    'body' => "<p>Hello {{FirstName}},</p><p>This is a bulk email message body for {$sub}.</p><p>Regards,<br>Team</p>",
                    'sending_domain' => ($userKey === 'admin') ? 'marketing.example.com' : 'sales.example.com',
                    'from_address' => "{$userKey}@marketing.example.com",
                    'reply_to' => 'reply@reply.example.net',
                    'user_id' => $userId,
                    'total_requested' => $totalReq,
                    'total_approved' => $approvedTotal,
                    'total_blocked' => $blockedTotal,
                    'status' => 'completed',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                // Insert blocked recipient logs
                for ($j = 0; $j < $blockedOwner; $j++) {
                    RecipientLog::create([
                        'campaign_id' => $campaignId,
                        'email' => "wrong-owner-{$j}@" . $domains[array_rand($domains)],
                        'salesforce_record_id' => '003SF0000000_WO',
                        'salesforce_object' => 'Contact',
                        'record_owner_id' => ($userKey === 'user') ? 'admin' : 'user',
                        'decision' => 'blocked',
                        'decision_reason' => 'DIFFERENT_OWNER',
                        'delivery_status' => 'blocked',
                        'tracking_token' => (string) Str::uuid(),
                        'validated_at' => $createdAt,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }

                for ($j = 0; $j < $blockedOptout; $j++) {
                    RecipientLog::create([
                        'campaign_id' => $campaignId,
                        'email' => "optout-{$j}@" . $domains[array_rand($domains)],
                        'salesforce_record_id' => '003SF0000000_OO',
                        'salesforce_object' => 'Contact',
                        'record_owner_id' => $userKey,
                        'decision' => 'blocked',
                        'decision_reason' => 'EMAIL_OPT_OUT',
                        'delivery_status' => 'blocked',
                        'tracking_token' => (string) Str::uuid(),
                        'validated_at' => $createdAt,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }

                for ($j = 0; $j < $blockedSupp; $j++) {
                    RecipientLog::create([
                        'campaign_id' => $campaignId,
                        'email' => "suppressed-{$j}@" . $domains[array_rand($domains)],
                        'salesforce_record_id' => '003SF0000000_GS',
                        'salesforce_object' => 'Contact',
                        'record_owner_id' => $userKey,
                        'decision' => 'blocked',
                        'decision_reason' => 'GLOBAL_SUPPRESSION',
                        'delivery_status' => 'blocked',
                        'tracking_token' => (string) Str::uuid(),
                        'validated_at' => $createdAt,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }

                // Insert approved logs
                for ($j = 0; $j < $approvedTotal; $j++) {
                    $email = "recipient-{$j}-{$dayOffset}@" . $domains[array_rand($domains)];
                    $sfId = sprintf('003SF0000000%02d%02d', $j, $dayOffset);
                    $randVal = (float) rand(1, 100) / 100;

                    $status = 'delivered';
                    $err = null;
                    if ($randVal < 0.05) {
                        $status = 'failed';
                        $err = 'SMTP Connection Timeout';
                    } elseif ($randVal < 0.12) {
                        $status = 'bounce';
                        $err = '550 User Unknown';
                    } elseif ($randVal < 0.15) {
                        $status = 'spam_complaint';
                    } elseif ($randVal < 0.65) {
                        $status = 'opened';
                    }

                    $sentTime = $createdAt->copy()->addMinutes(rand(1, 10));

                    $log = RecipientLog::create([
                        'campaign_id' => $campaignId,
                        'email' => $email,
                        'salesforce_record_id' => $sfId,
                        'salesforce_object' => 'Contact',
                        'record_owner_id' => $userKey,
                        'decision' => 'approved',
                        'decision_reason' => null,
                        'delivery_status' => $status,
                        'provider_message_id' => 'msg-' . Str::random(12),
                        'error_message' => $err,
                        'tracking_token' => (string) Str::uuid(),
                        'validated_at' => $createdAt,
                        'sent_at' => $sentTime,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    if (in_array($status, ['opened', 'spam_complaint'])) {
                        $openTime = $sentTime->copy()->addMinutes(rand(5, 300));
                        $loc = $geoLocations[array_rand($geoLocations)];

                        RecipientOpen::create([
                            'recipient_log_id' => $log->id,
                            'opened_at' => $openTime,
                            'ip_address' => $loc[3],
                            'country' => $loc[0],
                            'region' => $loc[1],
                            'city' => $loc[2],
                            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
                            'created_at' => $openTime,
                            'updated_at' => $openTime,
                        ]);

                        if (rand(1, 10) <= 4) {
                            $clickTime = $openTime->copy()->addSeconds(rand(10, 600));
                            RecipientClick::create([
                                'recipient_log_id' => $log->id,
                                'clicked_at' => $clickTime,
                                'url' => 'https://marketing.example.com/promo-link',
                                'ip_address' => $loc[3],
                                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
                                'created_at' => $clickTime,
                                'updated_at' => $clickTime,
                            ]);
                        }

                        if (rand(1, 10) <= 1) {
                            InboundReply::create([
                                'campaign_id' => $campaignId,
                                'recipient_email' => $email,
                                'reply_subject' => "Re: {$sub}",
                                'reply_body' => "Thanks for the information. I am interested. Let's schedule a call.",
                                'received_at' => $openTime->copy()->addHours(rand(1, 5)),
                                'mapped_salesforce_record_id' => $sfId,
                                'mapped_owner_id' => $userKey,
                                'created_at' => $openTime,
                                'updated_at' => $openTime,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
