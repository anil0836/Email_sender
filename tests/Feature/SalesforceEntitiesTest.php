<?php

namespace Tests\Feature;

use App\Models\SalesforceAccount;
use App\Models\SalesforceContact;
use App\Models\SalesforceLead;
use App\Models\SalesforceSyncLog;
use App\Models\SalesforceUser;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesforceEntitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_salesforce_models_and_relationships()
    {
        // Create Salesforce User
        $sfUser = SalesforceUser::create([
            'salesforce_id' => '005TESTUSER001',
            'username' => 'testuser@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'is_active' => true,
            'user_type' => 'Standard',
        ]);

        // Create Salesforce Account
        $sfAccount = SalesforceAccount::create([
            'salesforce_id' => '001TESTACC001',
            'name' => 'Acme Corporation',
            'type' => 'Customer',
            'industry' => 'Technology',
            'phone' => '+1 555-0199',
            'owner_id' => $sfUser->salesforce_id,
        ]);

        // Create Salesforce Contact
        $sfContact = SalesforceContact::create([
            'salesforce_id' => '003TESTCON001',
            'account_id' => $sfAccount->salesforce_id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'name' => 'Jane Smith',
            'email' => 'jane.smith@acme.com',
            'phone' => '+1 555-0188',
            'lead_source' => 'Web',
            'owner_id' => $sfUser->salesforce_id,
        ]);

        // Create Salesforce Lead
        $sfLead = SalesforceLead::create([
            'salesforce_id' => '00QTESTLEAD001',
            'first_name' => 'Bob',
            'last_name' => 'Taylor',
            'name' => 'Bob Taylor',
            'company' => 'Taylor Enterprises',
            'email' => 'bob@taylor.com',
            'owner_id' => $sfUser->salesforce_id,
        ]);

        // Test relationships
        $this->assertEquals($sfAccount->id, $sfContact->account->id);
        $this->assertEquals(1, $sfAccount->contacts()->count());
        $this->assertEquals($sfUser->id, $sfAccount->owner->id);
        $this->assertEquals($sfUser->id, $sfContact->owner->id);
        $this->assertEquals($sfUser->id, $sfLead->owner->id);
        $this->assertEquals(1, $sfUser->accounts()->count());
        $this->assertEquals(1, $sfUser->contacts()->count());
        $this->assertEquals(1, $sfUser->leads()->count());

        // Test search scopes
        $this->assertEquals(1, SalesforceAccount::search('Acme')->count());
        $this->assertEquals(1, SalesforceContact::search('Jane')->count());
        $this->assertEquals(1, SalesforceUser::search('John')->count());
    }

    public function test_salesforce_sync_log_checkpoint_tracking()
    {
        SalesforceSyncLog::create([
            'object_type' => 'Account',
            'sync_type' => 'cron',
            'status' => 'success',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(8),
            'records_fetched' => 10,
            'records_created' => 10,
            'last_modified_checkpoint' => now()->subMinutes(8),
        ]);

        SalesforceSyncLog::create([
            'object_type' => 'Contact',
            'sync_type' => 'cron',
            'status' => 'success',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(4),
            'records_fetched' => 20,
            'records_created' => 20,
            'last_modified_checkpoint' => now()->subMinutes(4),
        ]);

        $accountCheckpoint = SalesforceSyncLog::getLatestSuccessfulSync('Account');
        $this->assertNotNull($accountCheckpoint);
        $this->assertEquals('Account', $accountCheckpoint->object_type);

        $contactCheckpoint = SalesforceSyncLog::getLatestSuccessfulSync('Contact');
        $this->assertNotNull($contactCheckpoint);
        $this->assertEquals('Contact', $contactCheckpoint->object_type);
    }

    public function test_guest_is_redirected_to_login_for_admin_salesforce_routes()
    {
        $this->get('/admin/salesforce-accounts')->assertRedirect('/login');
        $this->get('/admin/salesforce-contacts')->assertRedirect('/login');
        $this->get('/admin/salesforce-users')->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_admin_salesforce_routes()
    {
        $user = User::where('username', 'user')->first();
        $this->assertNotNull($user);

        $session = [
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'email' => $user->email,
        ];

        $this->withSession($session)->get('/admin/salesforce-accounts')->assertRedirect(route('dashboard_view'));
        $this->withSession($session)->get('/admin/salesforce-contacts')->assertRedirect(route('dashboard_view'));
        $this->withSession($session)->get('/admin/salesforce-users')->assertRedirect(route('dashboard_view'));
    }

    public function test_admin_can_access_admin_salesforce_views()
    {
        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin);

        $session = [
            'user_id' => $admin->id,
            'username' => $admin->username,
            'role' => $admin->role,
            'email' => $admin->email,
        ];

        $resAccounts = $this->withSession($session)->get('/admin/salesforce-accounts');
        $resAccounts->assertStatus(200);
        $resAccounts->assertSee('Salesforce Synchronized Accounts');

        $resContacts = $this->withSession($session)->get('/admin/salesforce-contacts');
        $resContacts->assertStatus(200);
        $resContacts->assertSee('Salesforce Synchronized Contacts');

        $resUsers = $this->withSession($session)->get('/admin/salesforce-users');
        $resUsers->assertStatus(200);
        $resUsers->assertSee('Salesforce Synchronized Users');
    }

    public function test_admin_can_query_sync_status_endpoints()
    {
        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin);

        $session = [
            'user_id' => $admin->id,
            'username' => $admin->username,
            'role' => $admin->role,
            'email' => $admin->email,
        ];

        $this->withSession($session)->getJson('/admin/salesforce-accounts/sync-status')
            ->assertStatus(200)
            ->assertJsonStructure(['latest_sync', 'total_local_accounts']);

        $this->withSession($session)->getJson('/admin/salesforce-contacts/sync-status')
            ->assertStatus(200)
            ->assertJsonStructure(['latest_sync', 'total_local_contacts']);

        $this->withSession($session)->getJson('/admin/salesforce-users/sync-status')
            ->assertStatus(200)
            ->assertJsonStructure(['latest_sync', 'total_local_users']);
    }
}
