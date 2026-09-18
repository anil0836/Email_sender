<?php

namespace Tests\Feature;

use App\Models\SalesforceAccount;
use App\Models\SalesforceContact;
use App\Models\SalesforceLead;
use App\Models\SalesforceSfUser;
use App\Models\SalesforceSyncLog;
use App\Models\SalesforceUser;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesforceSfUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_sf_user_model_and_prime_owner_relationships()
    {
        // 1. Create Standard User
        $stdUser = SalesforceUser::create([
            'salesforce_id' => '005STDUSER001',
            'username' => 'stduser@example.com',
            'name' => 'Admin Standard',
            'email' => 'std@example.com',
        ]);

        // 2. Create SF_User__c (Custom User)
        $sfUser = SalesforceSfUser::create([
            'salesforce_id' => 'a04SFUSER001',
            'name' => 'Alex',
            'emp_name' => 'Alex Rivera',
            'full_name_in' => 'Alok Rawat',
            'emp_email' => 'alex@company.com',
            'emp_code' => '101',
            'process' => 'Sales',
            'team' => 'Alpha',
            'location' => 'US',
            'is_active' => true,
            'owner_id' => $stdUser->salesforce_id,
        ]);

        // 3. Create Account linked to SF_User__c as Prime Owner
        $account = SalesforceAccount::create([
            'salesforce_id' => '001ACC001',
            'name' => 'Apex Global Ltd',
            'prime_owner_id' => $sfUser->salesforce_id,
            'owner_id' => $stdUser->salesforce_id,
        ]);

        // 4. Create Contact linked to Account and SF_User__c as Prime Owner
        $contact = SalesforceContact::create([
            'salesforce_id' => '003CON001',
            'account_id' => $account->salesforce_id,
            'name' => 'Sarah Connor',
            'email' => 'sarah@apex.com',
            'prime_owner_id' => $sfUser->salesforce_id,
            'owner_id' => $stdUser->salesforce_id,
        ]);

        // 5. Create Lead linked to SF_User__c as Prime Owner
        $lead = SalesforceLead::create([
            'salesforce_id' => '00QLEAD001',
            'name' => 'Michael Scott',
            'company' => 'Dunder Mifflin',
            'email' => 'michael@dunder.com',
            'prime_owner_id' => $sfUser->salesforce_id,
            'owner_id' => $stdUser->salesforce_id,
        ]);

        // Verify relationships
        $this->assertEquals($sfUser->id, $account->primeOwner->id);
        $this->assertEquals($sfUser->id, $contact->primeOwner->id);
        $this->assertEquals($sfUser->id, $lead->primeOwner->id);

        $this->assertEquals(1, $sfUser->primeAccounts()->count());
        $this->assertEquals(1, $sfUser->primeContacts()->count());
        $this->assertEquals(1, $sfUser->primeLeads()->count());

        $this->assertEquals($stdUser->id, $sfUser->standardUser->id);

        // Verify scopes
        $this->assertEquals(1, SalesforceSfUser::search('Alex')->count());
        $this->assertEquals(1, SalesforceSfUser::search('101')->count());
        $this->assertEquals(1, SalesforceSfUser::filterActive('1')->count());
        $this->assertEquals(1, SalesforceSfUser::filterProcess('Sales')->count());
        $this->assertEquals(1, SalesforceSfUser::filterTeam('Alpha')->count());
    }

    public function test_guest_is_redirected_for_admin_sf_users_route()
    {
        $this->get('/admin/salesforce-sf-users')->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_admin_sf_users_route()
    {
        $user = User::where('username', 'user')->first();
        $this->assertNotNull($user);

        $session = [
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'email' => $user->email,
        ];

        $this->withSession($session)->get('/admin/salesforce-sf-users')->assertRedirect(route('dashboard_view'));
    }

    public function test_admin_can_access_admin_sf_users_view()
    {
        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin);

        $session = [
            'user_id' => $admin->id,
            'username' => $admin->username,
            'role' => $admin->role,
            'email' => $admin->email,
        ];

        $response = $this->withSession($session)->get('/admin/salesforce-sf-users');
        $response->assertStatus(200);
        $response->assertSee('Salesforce Custom Users (SF_User__c)');
    }

    public function test_admin_can_query_sf_users_sync_status()
    {
        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin);

        $session = [
            'user_id' => $admin->id,
            'username' => $admin->username,
            'role' => $admin->role,
            'email' => $admin->email,
        ];

        $response = $this->withSession($session)->getJson('/admin/salesforce-sf-users/sync-status');
        $response->assertStatus(200);
        $response->assertJsonStructure(['latest_sync', 'total_local_sf_users']);
    }
}
