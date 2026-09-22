<?php

namespace Tests\Feature;

use App\Models\GlobalSuppression;
use App\Models\User;
use App\Services\EmailSuppressionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmailSuppressionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $standardUser;
    protected EmailSuppressionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('username', 'admin')->first();
        $this->standardUser = User::where('username', 'user')->first();
        $this->service = app(EmailSuppressionService::class);
    }

    /**
     * Test normalization and batch checking in EmailSuppressionService.
     */
    public function test_email_suppression_service_normalization_and_batch_checks(): void
    {
        $this->service->suppress('  TEST.USER@EXAMPLE.COM  ', 'Test Reason', 'manual');

        $this->assertTrue($this->service->isSuppressed('test.user@example.com'));
        $this->assertTrue($this->service->isSuppressed('TEST.USER@EXAMPLE.COM'));
        $this->assertTrue($this->service->isSuppressed('  Test.User@Example.Com  '));
        $this->assertFalse($this->service->isSuppressed('other@example.com'));

        $batch = $this->service->getSuppressedInBatch([
            'TEST.USER@EXAMPLE.COM',
            'allowed1@example.com',
            'allowed2@example.com',
        ]);

        $this->assertEquals(['test.user@example.com'], $batch);
    }

    /**
     * Test admin can view suppressions directory with statistics and search.
     */
    public function test_admin_can_view_suppressions_directory(): void
    {
        $this->service->suppress('alpha@example.com', 'Unsubscribed', 'pabbly_webhook');
        $this->service->suppress('beta@example.com', 'Spam Complaint', 'pabbly_webhook');

        $response = $this->actingAs($this->admin)
            ->withSession([
                'user_id' => $this->admin->id,
                'username' => $this->admin->username,
                'role' => $this->admin->role,
            ])
            ->get('/admin/email-suppressions');

        $response->assertStatus(200);
        $response->assertSee('Global Email Suppressions');
        $response->assertSee('alpha@example.com');
        $response->assertSee('beta@example.com');
        $response->assertSee('Active Suppressed');
    }

    /**
     * Test admin can manually suppress an email.
     */
    public function test_admin_can_manually_suppress_email(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession([
                'user_id' => $this->admin->id,
                'username' => $this->admin->username,
                'role' => $this->admin->role,
            ])
            ->post('/admin/email-suppressions', [
                'email' => 'manual.blocked@example.com',
                'reason' => 'Administrative Block',
                'note' => 'Client requested block via call',
            ]);

        $response->assertRedirect('/admin/email-suppressions');
        $response->assertSessionHas('success');

        $this->assertTrue($this->service->isSuppressed('manual.blocked@example.com'));
        $record = GlobalSuppression::where('normalized_email', 'manual.blocked@example.com')->first();
        $this->assertNotNull($record);
        $this->assertEquals('admin_manual', $record->source);
        $this->assertEquals('suppressed', $record->status);
    }

    /**
     * Test admin can explicitly resubscribe an email address.
     */
    public function test_admin_can_explicitly_resubscribe_email(): void
    {
        $suppression = $this->service->suppress('resub.test@example.com', 'Unsubscribed', 'pabbly_webhook');
        $this->assertTrue($this->service->isSuppressed('resub.test@example.com'));

        $response = $this->actingAs($this->admin)
            ->withSession([
                'user_id' => $this->admin->id,
                'username' => $this->admin->username,
                'role' => $this->admin->role,
            ])
            ->post("/admin/email-suppressions/{$suppression->id}/resubscribe", [
                'remark' => 'Client signed new consent agreement',
            ]);

        $response->assertRedirect('/admin/email-suppressions');
        $response->assertSessionHas('success');

        // Sending eligibility must be restored
        $this->assertFalse($this->service->isSuppressed('resub.test@example.com'));

        $suppression->refresh();
        $this->assertEquals('resubscribed', $suppression->status);
        $this->assertNotNull($suppression->resubscribed_at);
        $this->assertEquals($this->admin->id, $suppression->resubscribed_by);
        $this->assertStringContainsString('Client signed new consent agreement', json_encode($suppression->metadata));
    }

    /**
     * Test CSV bulk suppression import.
     */
    public function test_bulk_suppression_csv_import(): void
    {
        // Pre-suppress one address to test duplicate detection
        $this->service->suppress('existing@example.com', 'Unsubscribed', 'pabbly_webhook');

        $csvContent = "email,reason\n"
            . "new1@example.com,Customer Request\n"
            . "new2@example.com,GDPR Opt-Out\n"
            . "existing@example.com,Duplicate Entry\n"
            . "new1@example.com,Duplicate Inside File\n"
            . "invalid-email-format,Invalid Syntax\n";

        $file = UploadedFile::fake()->createWithContent('suppressions.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->withSession([
                'user_id' => $this->admin->id,
                'username' => $this->admin->username,
                'role' => $this->admin->role,
            ])
            ->post('/admin/email-suppressions/import', [
                'file' => $file,
                'default_reason' => 'Bulk CSV Import',
            ]);

        $response->assertRedirect('/admin/email-suppressions/import');
        $response->assertSessionHas('import_summary');

        $summary = session('import_summary');
        $this->assertEquals(2, $summary['newly_suppressed']);
        $this->assertEquals(1, $summary['already_suppressed']);
        $this->assertEquals(1, $summary['duplicate_in_file']);
        $this->assertEquals(1, $summary['invalid_emails']);

        $this->assertTrue($this->service->isSuppressed('new1@example.com'));
        $this->assertTrue($this->service->isSuppressed('new2@example.com'));
    }

    /**
     * Test unauthorized users cannot access admin suppression routes.
     */
    public function test_standard_user_cannot_access_suppression_management(): void
    {
        $response = $this->actingAs($this->standardUser)
            ->withSession([
                'user_id' => $this->standardUser->id,
                'username' => $this->standardUser->username,
                'role' => $this->standardUser->role,
            ])
            ->get('/admin/email-suppressions');

        $response->assertRedirect(route('dashboard_view'));
        $response->assertSessionHas('danger', 'Unauthorized access.');

        $jsonResponse = $this->actingAs($this->standardUser)
            ->withSession([
                'user_id' => $this->standardUser->id,
                'username' => $this->standardUser->username,
                'role' => $this->standardUser->role,
            ])
            ->getJson('/admin/email-suppressions');

        $jsonResponse->assertStatus(403);
    }
}
