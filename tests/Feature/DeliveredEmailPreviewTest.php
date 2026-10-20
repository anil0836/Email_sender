<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\EmailAttachment;
use App\Models\RecipientLog;
use App\Models\SalesforceLead;
use App\Models\User;
use App\Models\UserSignature;
use App\Services\EmailRendererService;
use App\Services\PabblyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class DeliveredEmailPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private EmailRendererService $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create([
            'role' => 'user',
            'name' => 'Ben Manager',
            'username' => 'ben_mgr',
            'email' => 'ben@b2bexportsllc.com',
            'daily_limit' => 1000,
        ]);

        $this->renderer = app(EmailRendererService::class);
    }

    private function actingAsUser(): static
    {
        \Illuminate\Support\Facades\Auth::logout();
        return $this->actingAs($this->user)->withSession([
            'user_id' => $this->user->id,
            'username' => $this->user->username,
            'role' => $this->user->role,
            'email' => $this->user->email,
        ]);
    }

    public function test_campaign_with_plain_text_only(): void
    {
        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subject' => 'Simple Plain Notice',
            'body' => 'This is a simple plain text email body for testing.',
            'sending_domain' => 'b2bexportsllc.com',
            'from_address' => 'ben@b2bexportsllc.com',
            'reply_to' => 'ben@b2bexportsllc.com',
            'status' => 'completed',
        ]);

        $recipient = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'alice@example.com',
            'salesforce_record_id' => '00Q00000001',
            'salesforce_object' => 'Lead',
            'record_owner_id' => '00500000001',
            'decision' => 'approved',
            'delivery_status' => 'delivered',
            'tracking_token' => (string) Str::uuid(),
        ]);

        $result = $this->renderer->render($campaign, $recipient, $this->user, false);

        $this->assertStringContainsString('This is a simple plain text email body for testing.', $result['html']);
        $this->assertStringContainsString('alice@example.com', $result['html']);
        $this->assertStringContainsString($recipient->tracking_token, $result['html']);
        $this->assertStringNotContainsString('/track/open/', $result['html'], 'Preview must not contain open tracking pixel');
    }

    public function test_campaign_with_html_formatting(): void
    {
        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subject' => 'HTML Styled Announcement',
            'body' => '<div style="background-color: #f1f5f9; padding: 20px;"><h2 style="color: #1e3a8a;">Exciting Updates</h2><p>Our catalog has expanded.</p></div>',
            'sending_domain' => 'b2bexportsllc.com',
            'from_address' => 'ben@b2bexportsllc.com',
            'reply_to' => 'ben@b2bexportsllc.com',
            'status' => 'completed',
        ]);

        $result = $this->renderer->render($campaign, null, $this->user, false);

        $this->assertStringContainsString('<h2 style="color: #1e3a8a;">Exciting Updates</h2>', $result['html']);
        $this->assertStringContainsString('<!DOCTYPE html>', $result['full_document']);
        $this->assertStringContainsString('HTML Styled Announcement', $result['full_document']);
    }

    public function test_campaign_with_personalized_variables(): void
    {
        $lead = SalesforceLead::create([
            'salesforce_id' => '00QTEST00001',
            'first_name' => 'Sarah',
            'last_name' => 'Connor',
            'name' => 'Sarah Connor',
            'email' => 'sarah@cyberdyne.com',
            'company' => 'Cyberdyne Systems',
            'status' => 'Open',
        ]);

        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subject' => 'Hello {{FirstName}} from {{CompanyName}}',
            'body' => 'Hi {{FirstName}} {{LastName}},\n\nWe wanted to reach out regarding {{CompanyName}}.\nBest regards,\n{{SenderName}}',
            'sending_domain' => 'b2bexportsllc.com',
            'from_address' => 'ben@b2bexportsllc.com',
            'reply_to' => 'ben@b2bexportsllc.com',
            'status' => 'completed',
        ]);

        $recipient = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'sarah@cyberdyne.com',
            'salesforce_record_id' => $lead->salesforce_id,
            'salesforce_object' => 'Lead',
            'record_owner_id' => '00500000001',
            'decision' => 'approved',
            'delivery_status' => 'sent',
            'tracking_token' => (string) Str::uuid(),
        ]);

        $result = $this->renderer->render($campaign, $recipient, $this->user, false);

        $this->assertEquals('Hello Sarah from Cyberdyne Systems', $result['subject']);
        $this->assertStringContainsString('Hi Sarah Connor', $result['html']);
        $this->assertStringContainsString('Cyberdyne Systems', $result['html']);
        $this->assertStringContainsString('Ben Manager', $result['html']);
        $this->assertStringNotContainsString('{{FirstName}}', $result['html']);
        $this->assertStringNotContainsString('{{CompanyName}}', $result['html']);
    }

    public function test_campaign_with_missing_personalization_data(): void
    {
        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subject' => 'Greetings {{FirstName}}',
            'body' => 'Hi {{FirstName}},\n\nThank you for reaching out.',
            'sending_domain' => 'b2bexportsllc.com',
            'from_address' => 'ben@b2bexportsllc.com',
            'reply_to' => 'ben@b2bexportsllc.com',
            'status' => 'completed',
        ]);

        // Recipient without first name
        $recipient = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'contact@unknown.com',
            'salesforce_record_id' => 'RAW_123',
            'salesforce_object' => 'Lead',
            'record_owner_id' => '00500000001',
            'decision' => 'approved',
            'delivery_status' => 'sent',
            'tracking_token' => (string) Str::uuid(),
        ]);

        $result = $this->renderer->render($campaign, $recipient, $this->user, false);

        // Fallback for "Hi {{FirstName}}," should cleanly become "Hi,"
        $this->assertStringContainsString('Hi,', $result['html']);
        $this->assertStringNotContainsString('{{FirstName}}', $result['html']);
    }

    public function test_campaign_with_sender_signature(): void
    {
        $sig = UserSignature::create([
            'user_id' => $this->user->id,
            'name' => 'Work Sig',
            'sender_name' => 'Ben Manager',
            'job_title' => 'Procurement Director',
            'company_name' => 'B2B Exports LLC',
            'email' => 'ben@b2bexportsllc.com',
            'phone' => '+1 (555) 123-4567',
            'layout_style' => 'standard',
            'content' => '',
            'is_default' => true,
            'is_active' => true,
        ]);

        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subject' => 'Signed Campaign',
            'body' => 'We are pleased to introduce our products.',
            'signature_id' => $sig->id,
            'signature_snapshot' => $sig->renderHtml(),
            'sending_domain' => 'b2bexportsllc.com',
            'from_address' => 'ben@b2bexportsllc.com',
            'reply_to' => 'ben@b2bexportsllc.com',
            'status' => 'completed',
        ]);

        $recipient = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'buyer@partner.com',
            'salesforce_record_id' => '00Q00000002',
            'salesforce_object' => 'Lead',
            'record_owner_id' => '00500000001',
            'decision' => 'approved',
            'delivery_status' => 'sent',
            'tracking_token' => (string) Str::uuid(),
        ]);

        $result = $this->renderer->render($campaign, $recipient, $this->user, false);

        $this->assertStringContainsString('Procurement Director', $result['html']);
        $this->assertStringContainsString('B2B Exports LLC', $result['html']);
        $this->assertStringContainsString('+1 (555) 123-4567', $result['html']);

        // Test duplicate signature prevention
        $count = substr_count($result['html'], 'Procurement Director');
        $this->assertEquals(1, $count, 'Signature should appear exactly once, never duplicated');
    }

    public function test_campaign_with_one_attachment(): void
    {
        $campaignId = (string) Str::uuid();

        // Create physical file in fake storage
        Storage::disk('local')->put("attachments/{$campaignId}/catalog.pdf", 'dummy pdf binary data');

        $campaign = Campaign::create([
            'id' => $campaignId,
            'user_id' => $this->user->id,
            'subject' => 'Product Catalog Attached',
            'body' => 'Please find attached our latest product catalog.',
            'attachments' => json_encode(['catalog.pdf']),
            'sending_domain' => 'b2bexportsllc.com',
            'from_address' => 'ben@b2bexportsllc.com',
            'reply_to' => 'ben@b2bexportsllc.com',
            'status' => 'completed',
        ]);

        $recipient = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'client@acme.com',
            'salesforce_record_id' => '00Q00000003',
            'salesforce_object' => 'Lead',
            'record_owner_id' => '00500000001',
            'decision' => 'approved',
            'delivery_status' => 'delivered',
            'tracking_token' => (string) Str::uuid(),
        ]);

        $result = $this->renderer->render($campaign, $recipient, $this->user, false);

        $this->assertTrue($result['has_attachments']);
        $this->assertEquals(1, $result['attachments_count']);
        $this->assertStringContainsString('📎 Download Your Attachment (1):', $result['html']);
        $this->assertStringContainsString('catalog.pdf', $result['html']);
        $this->assertStringContainsString('Click Here to Download', $result['html']);
        $this->assertStringContainsString('Direct download link &bull; No login or account required.', $result['html']);
        $this->assertStringContainsString('/attachments/download/', $result['html']);
    }

    public function test_campaign_with_multiple_attachments(): void
    {
        $campaignId = (string) Str::uuid();

        Storage::disk('local')->put("attachments/{$campaignId}/pricelist.xlsx", 'data');
        Storage::disk('local')->put("attachments/{$campaignId}/brochure.pdf", 'data');

        $campaign = Campaign::create([
            'id' => $campaignId,
            'user_id' => $this->user->id,
            'subject' => 'Multiple Files Attached',
            'body' => 'Please download all materials below.',
            'attachments' => json_encode(['pricelist.xlsx', 'brochure.pdf']),
            'sending_domain' => 'b2bexportsllc.com',
            'from_address' => 'ben@b2bexportsllc.com',
            'reply_to' => 'ben@b2bexportsllc.com',
            'status' => 'completed',
        ]);

        $result = $this->renderer->render($campaign, null, $this->user, false);

        $this->assertTrue($result['has_attachments']);
        $this->assertEquals(2, $result['attachments_count']);
        $this->assertStringContainsString('📎 Download Your Attachment (2):', $result['html']);
        $this->assertStringContainsString('pricelist.xlsx', $result['html']);
        $this->assertStringContainsString('brochure.pdf', $result['html']);
    }

    public function test_campaign_without_attachments(): void
    {
        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subject' => 'No Attachments',
            'body' => 'A clean text email without attachments.',
            'attachments' => null,
            'sending_domain' => 'b2bexportsllc.com',
            'from_address' => 'ben@b2bexportsllc.com',
            'reply_to' => 'ben@b2bexportsllc.com',
            'status' => 'completed',
        ]);

        $result = $this->renderer->render($campaign, null, $this->user, false);

        $this->assertFalse($result['has_attachments']);
        $this->assertEquals(0, $result['attachments_count']);
        $this->assertStringNotContainsString('📎 Download Your Attachment', $result['html']);
    }

    public function test_campaign_with_unsubscribe_footer(): void
    {
        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subject' => 'Check Unsubscribe Footer',
            'body' => 'Hello there.',
            'sending_domain' => 'b2bexportsllc.com',
            'from_address' => 'ben@b2bexportsllc.com',
            'reply_to' => 'ben@b2bexportsllc.com',
            'status' => 'completed',
        ]);

        $token = (string) Str::uuid();
        $recipient = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'customer@enterprise.com',
            'salesforce_record_id' => '00Q00000004',
            'salesforce_object' => 'Lead',
            'record_owner_id' => '00500000001',
            'decision' => 'approved',
            'delivery_status' => 'sent',
            'tracking_token' => $token,
        ]);

        $result = $this->renderer->render($campaign, $recipient, $this->user, false);

        $this->assertStringContainsString('This email was sent to customer@enterprise.com', $result['html']);
        $this->assertStringContainsString("track/unsubscribe/{$token}", $result['html']);
        $this->assertStringContainsString('If you wish to unsubscribe, please', $result['html']);

        // Verify recipient status remains unchanged after preview
        $this->assertEquals('sent', $recipient->fresh()->delivery_status);
    }

    public function test_campaign_with_multiple_recipients_selection(): void
    {
        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subject' => 'Personalized for {{FirstName}}',
            'body' => 'Hello {{FirstName}},\nWelcome to our company!',
            'sending_domain' => 'b2bexportsllc.com',
            'from_address' => 'ben@b2bexportsllc.com',
            'reply_to' => 'ben@b2bexportsllc.com',
            'status' => 'completed',
        ]);

        $lead1 = SalesforceLead::create([
            'salesforce_id' => '00Q0001',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@smith.com',
            'company' => 'Smith Ltd',
        ]);

        $lead2 = SalesforceLead::create([
            'salesforce_id' => '00Q0002',
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'email' => 'bob@jones.com',
            'company' => 'Jones Inc',
        ]);

        $recipient1 = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'alice@smith.com',
            'salesforce_record_id' => $lead1->salesforce_id,
            'salesforce_object' => 'Lead',
            'record_owner_id' => '00500000001',
            'decision' => 'approved',
            'delivery_status' => 'delivered',
            'tracking_token' => (string) Str::uuid(),
        ]);

        $recipient2 = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'bob@jones.com',
            'salesforce_record_id' => $lead2->salesforce_id,
            'salesforce_object' => 'Lead',
            'record_owner_id' => '00500000001',
            'decision' => 'approved',
            'delivery_status' => 'delivered',
            'tracking_token' => (string) Str::uuid(),
        ]);

        // Render for Recipient 1 (Alice)
        $previewAlice = $this->renderer->render($campaign, $recipient1, $this->user, false);
        $this->assertEquals('Personalized for Alice', $previewAlice['subject']);
        $this->assertStringContainsString('Hello Alice', $previewAlice['html']);
        $this->assertStringContainsString('alice@smith.com', $previewAlice['html']);
        $this->assertStringNotContainsString('Bob', $previewAlice['html']);

        // Render for Recipient 2 (Bob)
        $previewBob = $this->renderer->render($campaign, $recipient2, $this->user, false);
        $this->assertEquals('Personalized for Bob', $previewBob['subject']);
        $this->assertStringContainsString('Hello Bob', $previewBob['html']);
        $this->assertStringContainsString('bob@jones.com', $previewBob['html']);
        $this->assertStringNotContainsString('Alice', $previewBob['html']);
    }

    public function test_draft_or_unsent_campaign_preview(): void
    {
        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subject' => 'Draft Subject for {{FirstName}}',
            'body' => 'Hi {{FirstName}},\nThis is a draft campaign.',
            'sending_domain' => 'b2bexportsllc.com',
            'from_address' => 'ben@b2bexportsllc.com',
            'reply_to' => 'ben@b2bexportsllc.com',
            'status' => 'draft',
        ]);

        // No recipient logs created yet
        $result = $this->renderer->render($campaign, null, $this->user, false);

        $this->assertTrue($result['is_sample']);
        $this->assertStringContainsString('Hi Valued,', $result['html']);
        $this->assertStringContainsString('recipient@example.com', $result['html']);
        $this->assertStringContainsString('This is a draft campaign.', $result['html']);
    }

    public function test_api_detail_and_preview_endpoints(): void
    {
        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subject' => 'Campaign with Endpoints',
            'body' => 'Hi {{FirstName}},\nTest endpoint preview.',
            'sending_domain' => 'b2bexportsllc.com',
            'from_address' => 'ben@b2bexportsllc.com',
            'reply_to' => 'ben@b2bexportsllc.com',
            'status' => 'completed',
        ]);

        $lead = SalesforceLead::create([
            'salesforce_id' => '00Q000ENDPOINT',
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'email' => 'charlie@peanuts.com',
            'company' => 'Peanuts Co',
        ]);

        $recipient = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'charlie@peanuts.com',
            'salesforce_record_id' => $lead->salesforce_id,
            'salesforce_object' => 'Lead',
            'record_owner_id' => '00500000001',
            'decision' => 'approved',
            'delivery_status' => 'delivered',
            'tracking_token' => (string) Str::uuid(),
        ]);

        // Test GET /api/campaign/{id}
        $response = $this->actingAsUser()->getJson("/api/campaign/{$campaign->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'campaign',
            'recipient_logs',
            'rendered_preview' => [
                'subject',
                'html',
                'full_document',
                'from_name',
                'from_email',
                'reply_to',
                'recipient_email',
                'recipient_name',
                'recipient_log_id',
                'has_attachments',
            ],
        ]);
        $json = $response->json();
        $expectedFromName = config('pabbly.from_name', 'anil patel');
        $this->assertEquals($expectedFromName, $json['rendered_preview']['from_name']);
        $this->assertEquals('ben@b2bexportsllc.com', $json['rendered_preview']['from_email']);
        $this->assertEquals('ben@b2bexportsllc.com', $json['rendered_preview']['reply_to']);
        $this->assertStringContainsString('Charlie', $json['rendered_preview']['html']);
        $this->assertStringContainsString('charlie@peanuts.com', $json['rendered_preview']['html']);

        // Test GET /api/campaign/{id}/preview?recipient_log_id={log_id}
        $previewResp = $this->actingAsUser()->getJson("/api/campaign/{$campaign->id}/preview?recipient_log_id={$recipient->id}");
        $previewResp->assertStatus(200);
        $previewResp->assertJsonPath('success', true);
        $previewJson = $previewResp->json();
        $this->assertStringContainsString('Charlie', $previewJson['preview']['html']);
        $this->assertEquals('charlie@peanuts.com', $previewJson['preview']['recipient_email']);
    }

    public function test_preview_does_not_trigger_email_sending_open_or_unsubscribe(): void
    {
        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subject' => 'Safety Verification',
            'body' => 'Testing safety guarantees.',
            'sending_domain' => 'b2bexportsllc.com',
            'from_address' => 'ben@b2bexportsllc.com',
            'reply_to' => 'ben@b2bexportsllc.com',
            'status' => 'completed',
        ]);

        $recipient = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'safety@guardian.com',
            'salesforce_record_id' => '00QSAFE01',
            'salesforce_object' => 'Lead',
            'record_owner_id' => '00500000001',
            'decision' => 'approved',
            'delivery_status' => 'sent',
            'tracking_token' => (string) Str::uuid(),
        ]);

        // Call preview multiple times
        for ($i = 0; $i < 3; $i++) {
            $response = $this->actingAsUser()->getJson("/api/campaign/{$campaign->id}/preview?recipient_log_id={$recipient->id}");
            $response->assertStatus(200);
        }

        // Recipient record must NOT change delivery_status
        $fresh = $recipient->fresh();
        $this->assertEquals('sent', $fresh->delivery_status);
        $this->assertNull($fresh->error_message);

        // No opens recorded
        $this->assertEquals(0, $recipient->opens()->count());
    }
}
