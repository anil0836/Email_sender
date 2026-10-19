<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\EmailAttachment;
use App\Models\RecipientLog;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\CampaignProcessingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicAttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected AttachmentService $attachmentService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->attachmentService = app(AttachmentService::class);

        $this->user = User::create([
            'username' => 'testadmin',
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
    }

    /**
     * Test Case 1: Logged-in user can download file via public token URL.
     */
    public function test_logged_in_user_can_download_file(): void
    {
        $fileContent = '%PDF-1.4 Mock PDF Content';
        $path = 'email-attachments/general/test_doc.pdf';
        Storage::disk('local')->put($path, $fileContent);

        $plainToken = Str::random(64);
        $attachment = EmailAttachment::create([
            'original_filename' => 'Company_Profile.pdf',
            'storage_path' => $path,
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($fileContent),
            'token_hash' => hash('sha256', $plainToken),
            'token' => $plainToken,
            'uploaded_by' => $this->user->id,
            'download_count' => 0,
        ]);

        $response = $this->actingAs($this->user)->get("/attachments/download/{$plainToken}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=Company_Profile.pdf');
        $this->assertEquals($fileContent, $response->streamedContent());

        $attachment->refresh();
        $this->assertEquals(1, $attachment->download_count);
        $this->assertNotNull($attachment->last_downloaded_at);
    }

    /**
     * Test Case 2: Logged-out user can download file without authentication.
     */
    public function test_logged_out_user_can_download_file_without_login(): void
    {
        $fileContent = 'Col1,Col2,Col3\nVal1,Val2,Val3';
        $path = 'email-attachments/general/pricing.csv';
        Storage::disk('local')->put($path, $fileContent);

        $plainToken = Str::random(64);
        $attachment = EmailAttachment::create([
            'original_filename' => 'pricing_data.csv',
            'storage_path' => $path,
            'disk' => 'local',
            'mime_type' => 'text/csv',
            'file_size' => strlen($fileContent),
            'token_hash' => hash('sha256', $plainToken),
            'token' => $plainToken,
            'download_count' => 0,
        ]);

        // Explicitly clear session / verify logged out
        $response = $this->flushSession()->get("/attachments/download/{$plainToken}");

        $response->assertStatus(200);
        $this->assertGuest();
        $response->assertHeader('Content-Disposition', 'attachment; filename=pricing_data.csv');
        $this->assertEquals($fileContent, $response->streamedContent());

        $attachment->refresh();
        $this->assertEquals(1, $attachment->download_count);
    }

    /**
     * Test Case 3: Recipient in incognito/private browsing mode downloads file successfully.
     */
    public function test_recipient_in_incognito_mode_downloads_file(): void
    {
        $fileContent = 'Binary Excel Mock Data';
        $path = 'email-attachments/general/catalog.xlsx';
        Storage::disk('local')->put($path, $fileContent);

        $plainToken = Str::random(64);
        $attachment = EmailAttachment::create([
            'original_filename' => 'Product_Catalog.xlsx',
            'storage_path' => $path,
            'disk' => 'local',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size' => strlen($fileContent),
            'token_hash' => hash('sha256', $plainToken),
            'token' => $plainToken,
            'download_count' => 0,
        ]);

        // Simulate request without cookies / incognito headers
        $response = $this->withHeaders([
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
        ])->get("/attachments/download/{$plainToken}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=Product_Catalog.xlsx');
        $this->assertEquals($fileContent, $response->streamedContent());
    }

    /**
     * Test Case 4: Recipient on mobile device downloads file successfully.
     */
    public function test_recipient_on_mobile_browser_downloads_file(): void
    {
        $fileContent = '%PDF-1.5 Mobile Invoice Sample';
        $path = 'email-attachments/general/mobile_invoice.pdf';
        Storage::disk('local')->put($path, $fileContent);

        $plainToken = Str::random(64);
        $attachment = EmailAttachment::create([
            'original_filename' => 'Invoice_2026.pdf',
            'storage_path' => $path,
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($fileContent),
            'token_hash' => hash('sha256', $plainToken),
            'token' => $plainToken,
            'download_count' => 0,
        ]);

        // Mobile iPhone Safari User-Agent
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ])->get("/attachments/download/{$plainToken}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=Invoice_2026.pdf');
        $this->assertEquals($fileContent, $response->streamedContent());
    }

    /**
     * Test Case 5: Invalid or non-existent token returns 404 Not Found.
     */
    public function test_invalid_token_returns_404(): void
    {
        $fakeToken = 'nonexistenttoken1234567890abcdef1234567890abcdef1234567890abcdef';
        $response = $this->get("/attachments/download/{$fakeToken}");

        $response->assertStatus(404);
        $response->assertSee('Attachment Not Found');
    }

    /**
     * Test Case 6: Deleted physical file returns 404 Not Found.
     */
    public function test_deleted_physical_file_returns_404(): void
    {
        $plainToken = Str::random(64);
        EmailAttachment::create([
            'original_filename' => 'Deleted_File.pdf',
            'storage_path' => 'email-attachments/general/does_not_exist_on_disk.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'token_hash' => hash('sha256', $plainToken),
            'token' => $plainToken,
            'download_count' => 0,
        ]);

        $response = $this->get("/attachments/download/{$plainToken}");

        $response->assertStatus(404);
    }

    /**
     * Test Case 7: Expired download link returns 410 Access Denied.
     */
    public function test_expired_link_returns_access_denied(): void
    {
        $fileContent = 'Expired content';
        $path = 'email-attachments/general/expired.pdf';
        Storage::disk('local')->put($path, $fileContent);

        $plainToken = Str::random(64);
        EmailAttachment::create([
            'original_filename' => 'Expired_Offer.pdf',
            'storage_path' => $path,
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($fileContent),
            'token_hash' => hash('sha256', $plainToken),
            'token' => $plainToken,
            'expires_at' => Carbon::now()->subHour(), // Expired 1 hour ago
            'download_count' => 0,
        ]);

        $response = $this->get("/attachments/download/{$plainToken}");

        $response->assertStatus(410);
        $response->assertSee('Access Denied');
        $response->assertSee('expired');
    }

    /**
     * Test Case 8: Manually revoked link returns 403 Access Denied.
     */
    public function test_revoked_link_returns_access_denied(): void
    {
        $fileContent = 'Revoked content';
        $path = 'email-attachments/general/revoked.pdf';
        Storage::disk('local')->put($path, $fileContent);

        $plainToken = Str::random(64);
        $attachment = EmailAttachment::create([
            'original_filename' => 'Revoked_Doc.pdf',
            'storage_path' => $path,
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($fileContent),
            'token_hash' => hash('sha256', $plainToken),
            'token' => $plainToken,
            'revoked_at' => Carbon::now(), // Revoked
            'download_count' => 0,
        ]);

        $response = $this->get("/attachments/download/{$plainToken}");

        $response->assertStatus(403);
        $response->assertSee('Access Denied');
        $response->assertSee('revoked');
    }

    /**
     * Test Case 9: Unauthorized access to admin pages remains strictly protected.
     */
    public function test_admin_and_management_pages_remain_protected(): void
    {
        $this->flushSession();

        // Admin pages
        $response = $this->get('/admin/users');
        $response->assertStatus(302);
        $response->assertRedirect('/login');

        // Campaign compose
        $response = $this->get('/campaign/new');
        $response->assertStatus(302);
        $response->assertRedirect('/login');

        // Dashboard
        $response = $this->get('/dashboard');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test Case 10: Valid Excel and PDF attachments download with correct headers and filenames.
     */
    public function test_valid_pdf_and_excel_attachments_download_correctly(): void
    {
        // 1. PDF Test
        $pdfContent = '%PDF-1.7 Test PDF binary stream';
        $pdfPath = 'email-attachments/general/quarterly_report.pdf';
        Storage::disk('local')->put($pdfPath, $pdfContent);

        $pdfToken = Str::random(64);
        EmailAttachment::create([
            'original_filename' => 'Quarterly_Report_Q3.pdf',
            'storage_path' => $pdfPath,
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdfContent),
            'token_hash' => hash('sha256', $pdfToken),
            'token' => $pdfToken,
        ]);

        $pdfResponse = $this->get("/attachments/download/{$pdfToken}");
        $pdfResponse->assertStatus(200);
        $pdfResponse->assertHeader('Content-Type', 'application/pdf');
        $pdfResponse->assertHeader('Content-Disposition', 'attachment; filename=Quarterly_Report_Q3.pdf');
        $this->assertEquals($pdfContent, $pdfResponse->streamedContent());

        // 2. Excel Test
        $xlsxContent = 'PK\x03\x04 Excel spreadsheet binary mockup';
        $xlsxPath = 'email-attachments/general/pricing_matrix.xlsx';
        Storage::disk('local')->put($xlsxPath, $xlsxContent);

        $xlsxToken = Str::random(64);
        EmailAttachment::create([
            'original_filename' => 'Pricing_Matrix_2026.xlsx',
            'storage_path' => $xlsxPath,
            'disk' => 'local',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size' => strlen($xlsxContent),
            'token_hash' => hash('sha256', $xlsxToken),
            'token' => $xlsxToken,
        ]);

        $xlsxResponse = $this->get("/attachments/download/{$xlsxToken}");
        $xlsxResponse->assertStatus(200);
        $xlsxResponse->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $xlsxResponse->assertHeader('Content-Disposition', 'attachment; filename=Pricing_Matrix_2026.xlsx');
        $this->assertEquals($xlsxContent, $xlsxResponse->streamedContent());
    }

    /**
     * Test Case 11: End-to-end integration: Campaign with attachment generates public link in email,
     * recipient clicks link and downloads file without login.
     */
    public function test_recipient_opens_link_from_email_body_without_login(): void
    {
        $campaignId = (string) Str::uuid();
        $filename = 'Client_Contract.pdf';
        $fileContent = '%PDF-1.4 Contract agreement data';

        // Save physical file
        $storagePath = "email-attachments/{$campaignId}/{$filename}";
        Storage::disk('local')->put($storagePath, $fileContent);

        $campaign = Campaign::create([
            'id' => $campaignId,
            'subject' => 'Please find attached contract',
            'body' => '<p>Hello, please review the contract below.</p>',
            'sending_domain' => 'b2bexports.in',
            'from_address' => 'deals@b2bexports.in',
            'reply_to' => 'deals@b2bexports.in',
            'user_id' => $this->user->id,
            'status' => 'queued',
            'attachments' => json_encode([$filename]),
        ]);

        $recipient = RecipientLog::create([
            'campaign_id' => $campaignId,
            'email' => 'client@customer.com',
            'salesforce_record_id' => 'RAW_001',
            'salesforce_object' => 'Raw',
            'record_owner_id' => '005XXXXXXXXXXXX',
            'decision' => 'approved',
            'delivery_status' => 'queued',
            'tracking_token' => Str::random(32),
        ]);

        // Process email queue
        $processingService = app(CampaignProcessingService::class);
        $processingService->processQueuedEmails();

        // Verify attachment record was generated with secure token
        $attachment = EmailAttachment::where('campaign_id', $campaignId)
            ->where('original_filename', $filename)
            ->first();

        $this->assertNotNull($attachment);
        $this->assertNotEmpty($attachment->token);
        $this->assertEquals(hash('sha256', $attachment->token), $attachment->token_hash);

        // Verify public download link works without any authentication
        $this->flushSession();
        $downloadUrl = $attachment->getDownloadUrl();
        $this->assertStringContainsString("/attachments/download/{$attachment->token}", $downloadUrl);

        $downloadResponse = $this->get($downloadUrl);
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('Content-Disposition', "attachment; filename={$filename}");
        $this->assertEquals($fileContent, $downloadResponse->streamedContent());
    }

    /**
     * Test Case 12: Directory traversal attack attempts are safely blocked.
     */
    public function test_directory_traversal_attempts_are_blocked(): void
    {
        $plainToken = Str::random(64);
        EmailAttachment::create([
            'original_filename' => 'secret.env',
            'storage_path' => '../../../../.env',
            'disk' => 'local',
            'token_hash' => hash('sha256', $plainToken),
            'token' => $plainToken,
        ]);

        $response = $this->get("/attachments/download/{$plainToken}");
        $response->assertStatus(404);
    }

    /**
     * Test Case 13: Legacy download route works without authentication for backward compatibility.
     */
    public function test_legacy_download_route_works_without_auth(): void
    {
        $campaignId = (string) Str::uuid();
        $filename = 'Legacy_Brochure.pdf';
        $fileContent = '%PDF-1.4 Legacy PDF content';

        Storage::disk('local')->put("attachments/{$campaignId}/{$filename}", $fileContent);

        Campaign::create([
            'id' => $campaignId,
            'subject' => 'Old Campaign',
            'body' => '<p>Old</p>',
            'sending_domain' => 'b2bexports.in',
            'from_address' => 'info@b2bexports.in',
            'reply_to' => 'info@b2bexports.in',
            'user_id' => $this->user->id,
            'status' => 'completed',
            'attachments' => json_encode([$filename]),
        ]);

        $encodedFilename = rawurlencode($filename);
        $response = $this->flushSession()->get("/download/attachment/{$campaignId}/{$encodedFilename}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', "attachment; filename={$filename}");
        $this->assertEquals($fileContent, $response->streamedContent());
    }

    /**
     * Test Case 14: JSON clients receive structured JSON error responses.
     */
    public function test_json_clients_receive_structured_error_responses(): void
    {
        // 404 Not Found as JSON
        $response = $this->getJson('/attachments/download/nonexistent1234567890abcdef');
        $response->assertStatus(404);
        $response->assertJsonStructure(['error', 'status']);

        // 403 Revoked as JSON
        $plainToken = Str::random(64);
        EmailAttachment::create([
            'original_filename' => 'Revoked_API.pdf',
            'storage_path' => 'email-attachments/general/dummy.pdf',
            'disk' => 'local',
            'token_hash' => hash('sha256', $plainToken),
            'token' => $plainToken,
            'revoked_at' => Carbon::now(),
        ]);

        $response = $this->getJson("/attachments/download/{$plainToken}");
        $response->assertStatus(403);
        $response->assertJsonFragment(['error' => 'Access denied']);
    }

    /**
     * Test Case 15: AttachmentService storeUploadedFile correctly saves file and generates token.
     */
    public function test_attachment_service_store_uploaded_file(): void
    {
        $uploadedFile = UploadedFile::fake()->create('Company_Catalog.pdf', 500, 'application/pdf');

        $attachment = $this->attachmentService->storeUploadedFile($uploadedFile, null, $this->user->id);

        $this->assertNotNull($attachment);
        $this->assertEquals('Company_Catalog.pdf', $attachment->original_filename);
        $this->assertEquals('application/pdf', $attachment->mime_type);
        $this->assertNotEmpty($attachment->token);
        $this->assertEquals(hash('sha256', $attachment->token), $attachment->token_hash);
        $this->assertTrue(Storage::disk('local')->exists($attachment->storage_path));

        // Test downloading the newly uploaded file via public token
        $response = $this->get("/attachments/download/{$attachment->token}");
        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=Company_Catalog.pdf');
    }

    /**
     * Test Case 16: Campaign send with uploaded attachment files succeeds and creates EmailAttachment records.
     */
    public function test_campaign_send_with_uploaded_attachment_files_succeeds(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('4267996426.pdf', 300, 'application/pdf');

        $response = $this->actingAs($this->user)->withSession([
            'user_id' => $this->user->id,
            'username' => $this->user->username,
            'role' => $this->user->role,
            'email' => $this->user->email,
        ])->post('/api/campaign/send', [
            'subject' => 'Campaign with Attachment',
            'body' => 'Please find attached catalog.',
            'recipient_emails' => ['client@example.com'],
            'attachment_files' => [$file],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $campaignId = $response->json('campaign_id');
        $this->assertNotNull($campaignId);

        // Verify EmailAttachment was created with foreign key campaign_id
        $this->assertDatabaseHas('email_attachments', [
            'campaign_id' => $campaignId,
            'original_filename' => '4267996426.pdf',
        ]);

        $att = EmailAttachment::where('campaign_id', $campaignId)->first();
        $this->assertNotNull($att);
        $this->assertNotEmpty($att->token);

        // Verify public download works for this attachment
        $downloadRes = $this->get("/attachments/download/{$att->token}");
        $downloadRes->assertOk();
        $downloadRes->assertHeader('Content-Disposition', 'attachment; filename=4267996426.pdf');
    }
}
