<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesforceLeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_is_redirected_to_login_when_accessing_salesforce_leads_page()
    {
        $response = $this->get('/salesforce/leads');
        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_access_salesforce_leads_api()
    {
        $response = $this->getJson('/api/salesforce/leads');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_salesforce_leads_view()
    {
        $user = User::where('username', 'user')->first();
        $this->assertNotNull($user);

        $response = $this->withSession([
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'email' => $user->email,
        ])->get('/salesforce/leads');

        $response->assertStatus(200);
        $response->assertSee('Salesforce Leads');
        $response->assertSee('Salesforce Lead Directory');
    }

    public function test_authenticated_user_can_query_leads_api()
    {
        $user = User::where('username', 'user')->first();
        $this->assertNotNull($user);

        $response = $this->withSession([
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'email' => $user->email,
        ])->getJson('/api/salesforce/leads?page=1&per_page=25');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'total',
            'limit',
            'offset',
            'records',
        ]);
    }

    public function test_authenticated_user_can_access_connection_test_view()
    {
        $user = User::where('username', 'user')->first();
        $this->assertNotNull($user);

        $response = $this->withSession([
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'email' => $user->email,
        ])->get('/salesforce/test-connection');

        $response->assertStatus(200);
        $response->assertSee('Salesforce Connection Test');
    }

    public function test_authenticated_user_can_query_test_connection_api()
    {
        $user = User::where('username', 'user')->first();
        $this->assertNotNull($user);

        $response = $this->withSession([
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'email' => $user->email,
        ])->getJson('/api/salesforce/test-connection');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
        ]);
    }

    public function test_admin_can_access_admin_salesforce_leads_view()
    {
        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin);

        $response = $this->withSession([
            'user_id' => $admin->id,
            'username' => $admin->username,
            'role' => $admin->role,
            'email' => $admin->email,
        ])->get('/admin/salesforce-leads');

        $response->assertStatus(200);
        $response->assertSee('Salesforce Synchronized Leads');
    }

    public function test_non_admin_cannot_access_admin_salesforce_leads()
    {
        $user = User::where('username', 'user')->first();
        $this->assertNotNull($user);

        $response = $this->withSession([
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'email' => $user->email,
        ])->get('/admin/salesforce-leads');

        $response->assertRedirect(route('dashboard_view'));
    }

    public function test_admin_can_trigger_manual_sync_via_ajax()
    {
        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin);

        $response = $this->withSession([
            'user_id' => $admin->id,
            'username' => $admin->username,
            'role' => $admin->role,
            'email' => $admin->email,
        ])->postJson('/admin/salesforce-leads/sync');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'total_fetched',
            'created',
            'updated',
        ]);
    }
}
