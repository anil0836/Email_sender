<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\CampaignTemplate;
use App\Models\RecipientLog;
use App\Models\SalesforceContact;
use App\Models\SalesforceLead;
use App\Models\User;
use App\Models\UserSignature;
use App\Services\CampaignMergeFieldService;
use App\Services\CampaignProcessingService;
use App\Services\PabblyService;
use App\Services\SalesforceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CampaignTemplateAndSignatureTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $standardUser;
    private CampaignMergeFieldService $mergeFieldService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create([
            'role' => 'line_manager',
            'username' => 'manager_alex',
            'daily_limit' => 5000,
        ]);

        $this->standardUser = User::factory()->create([
            'role' => 'user',
            'name' => 'John Sender',
            'username' => 'john_sender',
            'email' => 'john@sender.com',
            'manager_id' => $this->manager->id,
            'daily_limit' => 1000,
        ]);

        $this->mergeFieldService = new CampaignMergeFieldService();
    }

    private function actingAsStandardUser(): static
    {
        \Illuminate\Support\Facades\Auth::logout();
        return $this->actingAs($this->standardUser)->withSession([
            'user_id' => $this->standardUser->id,
            'username' => $this->standardUser->username,
            'role' => $this->standardUser->role,
            'email' => $this->standardUser->email,
        ]);
    }

    /**
     * 1. Test signature creation and management with structured fields.
     */
    public function test_user_can_create_and_manage_signatures_with_structured_fields(): void
    {
        $response = $this->actingAsStandardUser()->post(route('signatures.store'), [
            'name' => 'Corporate Signature',
            'sender_name' => 'John Sender',
            'job_title' => 'Senior Account Executive',
            'company_name' => 'B2B Exports LLC',
            'email' => 'john@b2bexportsllc.com',
            'phone' => '+1 (555) 123-4567',
            'website' => 'https://b2bexportsllc.com',
            'address' => "100 Broadway\nNew York, NY 10005",
            'is_default' => 1,
        ]);

        $response->assertRedirect(route('signatures.index'));
        $this->assertDatabaseHas('user_signatures', [
            'user_id' => $this->standardUser->id,
            'name' => 'Corporate Signature',
            'sender_name' => 'John Sender',
            'job_title' => 'Senior Account Executive',
            'company_name' => 'B2B Exports LLC',
            'is_default' => true,
        ]);

        $sig = UserSignature::where('name', 'Corporate Signature')->first();
        $this->assertNotNull($sig);

        // Verify JSON show endpoint
        $showRes = $this->actingAsStandardUser()->getJson(route('signatures.show', $sig->id));
        $showRes->assertOk();
        $showRes->assertJsonPath('signature.name', 'Corporate Signature');
        $this->assertStringContainsString('Senior Account Executive', $showRes->json('rendered_html'));
        $this->assertStringContainsString('B2B Exports LLC', $showRes->json('rendered_html'));

        // Test update signature
        $updateRes = $this->actingAsStandardUser()->putJson(route('signatures.update', $sig->id), [
            'name' => 'Updated Signature',
            'sender_name' => 'John Sender',
            'job_title' => 'VP of Strategic Alliances',
            'company_name' => 'B2B Global Exports LLC',
        ]);
        $updateRes->assertOk();
        $this->assertDatabaseHas('user_signatures', [
            'id' => $sig->id,
            'name' => 'Updated Signature',
            'job_title' => 'VP of Strategic Alliances',
        ]);
    }

    /**
     * 2. Test default signature toggling: setting a new default unsets previous default.
     */
    public function test_default_signature_toggling_unsets_previous_default(): void
    {
        $sig1 = UserSignature::create([
            'user_id' => $this->standardUser->id,
            'name' => 'Sig 1',
            'sender_name' => 'Sender One',
            'is_default' => true,
        ]);

        $this->assertTrue($sig1->fresh()->is_default);

        // Create Sig 2 with is_default = true
        $this->actingAsStandardUser()->postJson(route('signatures.store'), [
            'name' => 'Sig 2',
            'sender_name' => 'Sender Two',
            'is_default' => 1,
        ]);

        $this->assertFalse($sig1->fresh()->is_default);
        $sig2 = UserSignature::where('name', 'Sig 2')->first();
        $this->assertTrue($sig2->is_default);

        // Switch default back to sig1 via setDefault route
        $this->actingAsStandardUser()->postJson(route('signatures.default', $sig1->id));
        $this->assertTrue($sig1->fresh()->is_default);
        $this->assertFalse($sig2->fresh()->is_default);
    }

    /**
     * 3. Test Campaign Template CRUD via API endpoints.
     */
    public function test_user_can_create_read_update_delete_campaign_templates(): void
    {
        // 1. Create template
        $res = $this->actingAsStandardUser()->postJson('/api/templates', [
            'name' => 'Healthcare Outreach V1',
            'subject' => 'Partnership Inquiry for {{CompanyName}}',
            'body' => '<p>Hi {{FirstName}},</p><p>We help businesses like {{CompanyName}} grow.</p><p>{{Signature}}</p>',
        ]);

        $res->assertOk();
        $templateId = $res->json('template_id');
        $this->assertNotNull($templateId);

        $this->assertDatabaseHas('campaign_templates', [
            'id' => $templateId,
            'name' => 'Healthcare Outreach V1',
        ]);

        // 2. Fetch specific template
        $getRes = $this->actingAsStandardUser()->getJson("/api/templates/{$templateId}");
        $getRes->assertOk();
        $getRes->assertJsonPath('template.subject', 'Partnership Inquiry for {{CompanyName}}');

        // 3. Update template
        $updRes = $this->actingAsStandardUser()->putJson("/api/templates/{$templateId}", [
            'name' => 'Healthcare Outreach V2',
            'subject' => 'Updated Inquiry for {{CompanyName}}',
            'body' => '<p>Hi {{FirstName}}, updated body!</p>',
        ]);
        $updRes->assertOk();
        $this->assertDatabaseHas('campaign_templates', [
            'id' => $templateId,
            'name' => 'Healthcare Outreach V2',
            'subject' => 'Updated Inquiry for {{CompanyName}}',
        ]);

        // 4. Delete template
        $delRes = $this->actingAsStandardUser()->deleteJson("/api/templates/{$templateId}");
        $delRes->assertOk();
        $this->assertDatabaseMissing('campaign_templates', ['id' => $templateId]);
    }

    /**
     * 4. Test merge field resolution with Lead and Contact first names.
     */
    public function test_merge_field_service_resolves_lead_and_contact_first_names_correctly(): void
    {
        // Lead recipient
        $lead = new SalesforceLead([
            'salesforce_id' => '00QTEST0000001',
            'first_name' => 'Samantha',
            'last_name' => 'Carter',
            'company' => 'Stargate Tech',
            'email' => 'samantha@stargate.mil',
            'phone' => '+1-555-0100',
            'owner_name' => 'General Hammond',
        ]);

        $template = 'Hi {{FirstName}} {{LastName}}, thank you for contacting {{CompanyName}}. Owner: {{OwnerName}}';
        $resolved = $this->mergeFieldService->resolve($template, $lead, $this->standardUser);

        $this->assertEquals(
            'Hi Samantha Carter, thank you for contacting Stargate Tech. Owner: General Hammond',
            $resolved
        );

        // Contact recipient
        $contact = new SalesforceContact([
            'salesforce_id' => '003TEST0000001',
            'first_name' => 'Daniel',
            'last_name' => 'Jackson',
            'email' => 'daniel@archaeology.org',
            'phone' => '+1-555-0101',
            'owner_name' => 'Dr. Weir',
        ]);

        $resolvedContact = $this->mergeFieldService->resolve(
            'Dear {{FirstName}}, your email is {{Email}}',
            $contact
        );
        $this->assertEquals('Dear Daniel, your email is daniel@archaeology.org', $resolvedContact);
    }

    /**
     * 5. Test clean punctuation fallback when first name is null or blank.
     */
    public function test_merge_field_service_clean_punctuation_fallback_when_name_is_null_or_empty(): void
    {
        $recordWithoutFirstName = [
            'first_name' => '',
            'last_name' => '',
            'company' => 'Starlight Corp',
            'email' => 'contact@starlight.com',
        ];

        // "Hi {{FirstName}}," must fall back to "Hi," without any trailing or double space!
        $test1 = 'Hi {{FirstName}}, welcome to our platform.';
        $res1 = $this->mergeFieldService->resolve($test1, $recordWithoutFirstName);
        $this->assertEquals('Hi, welcome to our platform.', $res1);

        // "Hello {{FirstName}}!" must fall back to "Hello!"
        $test2 = 'Hello {{FirstName}}! Hope you are well.';
        $res2 = $this->mergeFieldService->resolve($test2, $recordWithoutFirstName);
        $this->assertEquals('Hello! Hope you are well.', $res2);

        // "Dear {{FirstName}}:" must fall back to "Dear:"
        $test3 = 'Dear {{FirstName}}: Please review this proposal.';
        $res3 = $this->mergeFieldService->resolve($test3, $recordWithoutFirstName);
        $this->assertEquals('Dear: Please review this proposal.', $res3);
    }

    /**
     * 6. Test all merge tokens including {{Signature}} and {{SenderName}}.
     */
    public function test_merge_field_service_resolves_all_tokens_including_signature_and_sender(): void
    {
        $sigHtml = '<div class="signature">Best regards,<br><strong>John Sender</strong></div>';
        $template = 'Hi {{FirstName}}, I am {{SenderName}} from {{CompanyName}}. Contact me at {{Phone}}. {{Signature}}';

        $recipient = [
            'first_name' => 'Robert',
            'last_name' => 'Picardo',
            'company' => 'Voyager Holograms',
            'phone' => '+1-800-VOYAGER',
        ];

        $resolved = $this->mergeFieldService->resolve($template, $recipient, $this->standardUser, $sigHtml);

        $this->assertStringContainsString('Hi Robert,', $resolved);
        $this->assertStringContainsString('I am John Sender', $resolved);
        $this->assertStringContainsString('from Voyager Holograms', $resolved);
        $this->assertStringContainsString('Contact me at +1-800-VOYAGER', $resolved);
        $this->assertStringContainsString($sigHtml, $resolved);
    }

    /**
     * 7. Test Campaign submission saves template_id, signature_id, and freezes signature snapshot,
     * while preserving the manager approval workflow.
     */
    public function test_campaign_creation_stores_template_signature_and_freezes_snapshot(): void
    {
        $tmpl = CampaignTemplate::create([
            'user_id' => $this->standardUser->id,
            'name' => 'Standard Outreach',
            'subject' => 'Quick question for {{FirstName}}',
            'body' => '<p>Hi {{FirstName}}, from {{CompanyName}}.</p>{{Signature}}',
        ]);

        $sig = UserSignature::create([
            'user_id' => $this->standardUser->id,
            'name' => 'Exec Signature',
            'sender_name' => 'John Sender',
            'job_title' => 'Managing Director',
            'company_name' => 'Acme Capital',
            'email' => 'john@acme.com',
            'is_default' => true,
        ]);

        // Standard user submits campaign
        $res = $this->actingAsStandardUser()->postJson('/api/campaign/send', [
            'subject' => $tmpl->subject,
            'body' => $tmpl->body,
            'template_id' => $tmpl->id,
            'signature_id' => $sig->id,
            'recipient_emails' => ['lead1@test.com'],
            'sending_domain' => 'proitbuyer.com',
            'from_address' => 'rma@proitbuyer.com',
        ]);

        $res->assertOk();
        $res->assertJsonPath('success', true);
        // Standard user MUST remain in pending_line_manager!
        $res->assertJsonPath('status', 'pending_line_manager');

        $campaignId = $res->json('campaign_id');
        $campaign = Campaign::find($campaignId);
        $this->assertNotNull($campaign);

        // Verify foreign keys and frozen snapshot
        $this->assertEquals($tmpl->id, $campaign->template_id);
        $this->assertEquals($sig->id, $campaign->signature_id);
        $this->assertNotNull($campaign->signature_snapshot);
        $this->assertStringContainsString('Managing Director', $campaign->signature_snapshot);
        $this->assertStringContainsString('Acme Capital', $campaign->signature_snapshot);

        // Verify that deleting the signature afterwards does NOT alter historical snapshot
        $sig->delete();
        $this->assertNotNull($campaign->fresh()->signature_snapshot);
        $this->assertStringContainsString('Managing Director', $campaign->fresh()->signature_snapshot);
    }

    /**
     * 8. Test Live Preview API endpoint.
     */
    public function test_api_preview_endpoint_returns_personalized_content(): void
    {
        $sig = UserSignature::create([
            'user_id' => $this->standardUser->id,
            'name' => 'Default Sig',
            'sender_name' => 'John Sender',
            'company_name' => 'Alpha Corp',
            'is_default' => true,
        ]);

        $res = $this->actingAsStandardUser()->postJson('/api/campaign/preview', [
            'subject' => 'Special offer for {{CompanyName}}',
            'body' => '<p>Hi {{FirstName}}, let us connect.</p>',
            'signature_id' => $sig->id,
        ]);

        $res->assertOk();
        $res->assertJsonPath('success', true);
        $this->assertStringContainsString('Alpha Corp', $res->json('resolved_body'));
    }

    /**
     * 9. Test Bulk Campaign dispatch personalizes each recipient uniquely.
     */
    public function test_bulk_campaign_dispatch_personalizes_each_recipient_uniquely(): void
    {
        $campaign = Campaign::create([
            'id' => (string) Str::uuid(),
            'subject' => 'Hello {{FirstName}}, news for {{CompanyName}}',
            'body' => '<p>Hi {{FirstName}},</p><p>We are reaching out to {{CompanyName}}.</p>',
            'sending_domain' => 'proitbuyer.com',
            'from_address' => 'rma@proitbuyer.com',
            'reply_to' => 'rma@proitbuyer.com',
            'user_id' => $this->standardUser->id,
            'status' => 'queued', // Simulated approved campaign
            'signature_snapshot' => '<div class="sig">Signed by John</div>',
            'total_approved' => 2,
            'total_requested' => 2,
        ]);

        // Create 2 recipient logs with different mock records
        $rec1 = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'alice@companyone.com',
            'salesforce_record_id' => '003MOCK0000001',
            'salesforce_object' => 'Contact',
            'record_owner_id' => $this->standardUser->username,
            'decision' => 'approved',
            'delivery_status' => 'queued',
            'tracking_token' => Str::random(32),
        ]);

        $rec2 = RecipientLog::create([
            'campaign_id' => $campaign->id,
            'email' => 'bob@companytwo.com',
            'salesforce_record_id' => '003MOCK0000002',
            'salesforce_object' => 'Contact',
            'record_owner_id' => $this->standardUser->username,
            'decision' => 'approved',
            'delivery_status' => 'queued',
            'tracking_token' => Str::random(32),
        ]);

        // Mock SalesforceService so checkRecipientEligibility returns distinct records
        $mockSfService = $this->createMock(SalesforceService::class);
        $mockSfService->method('checkRecipientEligibility')
            ->willReturnCallback(function (string $recordId, string $appUsername) {
                if ($recordId === '003MOCK0000001') {
                    return [true, null, [
                        'first_name' => 'Alice',
                        'last_name' => 'Wonderland',
                        'company' => 'Company One LLC',
                        'email' => 'alice@companyone.com',
                    ]];
                }
                return [true, null, [
                    'first_name' => 'Bob',
                    'last_name' => 'Builder',
                    'company' => 'Company Two Inc',
                    'email' => 'bob@companytwo.com',
                ]];
            });

        $mockPabbly = $this->createMock(PabblyService::class);

        $processingService = new CampaignProcessingService(
            $mockSfService,
            $mockPabbly,
            $this->mergeFieldService
        );

        $processed = $processingService->processQueuedEmails();
        $this->assertEquals(2, $processed);

        // Recipient logs should now be delivered/sent
        $this->assertEquals('sent', $rec1->fresh()->delivery_status);
        $this->assertEquals('sent', $rec2->fresh()->delivery_status);
        $this->assertEquals('completed', $campaign->fresh()->status);
    }

    /**
     * 10. Test resolution of CompanyWebsite, CompanyEmail, CompanyPhone and HTML table preservation.
     */
    public function test_merge_field_service_resolves_company_website_and_preserves_html_tables(): void
    {
        $sig = UserSignature::create([
            'user_id' => $this->standardUser->id,
            'name' => 'Support Sig',
            'sender_name' => 'Alice Approver',
            'company_name' => 'Acme Corp',
            'email' => 'support@acme.com',
            'phone' => '+1-800-555-0199',
            'website' => 'https://acme.com',
            'is_default' => true,
            'is_active' => true,
        ]);

        $lead = new SalesforceLead([
            'salesforce_id' => '00QTEST999',
            'first_name' => 'Jonny',
            'company' => 'R2V3 Solutions',
            'email' => 'jonny@r2v3.com',
            'website' => 'https://r2v3solutions.com',
        ]);

        $tableTemplate = '<table width="100%"><tr><td align="center"><table width="600" style="max-width:600px;"><tr><td>Hi {{FirstName}}, visit <a href="{{CompanyWebsite}}">Site</a>. Contact {{CompanyEmail}} | {{CompanyPhone}}</td></tr></table></td></tr></table>';

        $resolved = $this->mergeFieldService->resolve($tableTemplate, $lead, $this->standardUser);

        $this->assertStringContainsString('Hi Jonny,', $resolved);
        $this->assertStringContainsString('href="https://r2v3solutions.com"', $resolved);
        $this->assertStringContainsString('support@acme.com', $resolved);
        $this->assertStringContainsString('+1-800-555-0199', $resolved);
        $this->assertStringContainsString('<table width="600" style="max-width:600px;">', $resolved);
    }
}
