<?php

namespace App\Services;

use App\Models\CampaignMember;
use App\Models\RecipientLog;
use App\Models\SalesforceAccount;
use App\Models\SalesforceContact;
use App\Models\SalesforceLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CampaignMergeFieldService
{
    /**
     * List of all supported merge field tags and their descriptions.
     */
    public function getAvailableMergeFields(): array
    {
        return [
            [
                'tag' => '{{FirstName}}',
                'label' => 'First Name',
                'description' => "Recipient's first name (Lead/Contact)",
                'example' => 'John',
            ],
            [
                'tag' => '{{LastName}}',
                'label' => 'Last Name',
                'description' => "Recipient's last name",
                'example' => 'Doe',
            ],
            [
                'tag' => '{{FullName}}',
                'label' => 'Full Name',
                'description' => "Recipient's full name",
                'example' => 'John Doe',
            ],
            [
                'tag' => '{{CompanyName}}',
                'label' => 'Company Name',
                'description' => "Recipient's company or account name",
                'example' => 'Acme Corp',
            ],
            [
                'tag' => '{{Email}}',
                'label' => 'Email Address',
                'description' => "Recipient's email address",
                'example' => 'john.doe@example.com',
            ],
            [
                'tag' => '{{Phone}}',
                'label' => 'Phone',
                'description' => "Recipient's phone number",
                'example' => '+1 (555) 234-5678',
            ],
            [
                'tag' => '{{OwnerName}}',
                'label' => 'Owner Name',
                'description' => 'Salesforce assigned record owner name',
                'example' => 'Sarah Connor',
            ],
            [
                'tag' => '{{CompanyWebsite}}',
                'label' => 'Company Website',
                'description' => "Recipient or company website URL",
                'example' => 'https://example.com',
            ],
            [
                'tag' => '{{CompanyEmail}}',
                'label' => 'Company Email',
                'description' => "Company contact email",
                'example' => 'info@example.com',
            ],
            [
                'tag' => '{{CompanyPhone}}',
                'label' => 'Company Phone',
                'description' => "Company phone number",
                'example' => '+1 (555) 123-4567',
            ],
            [
                'tag' => '{{SenderName}}',
                'label' => 'Sender Name',
                'description' => 'Name of the current sending user',
                'example' => 'Alex Smith',
            ],
            [
                'tag' => '{{Signature}}',
                'label' => 'Email Signature',
                'description' => 'Rendered HTML signature block',
                'example' => '[Signature Block]',
            ],
        ];
    }

    /**
     * Normalizes recipient data from various model types or arrays.
     */
    public function extractRecipientData(array|Model|null $recipient): array
    {
        if (!$recipient) {
            return [
                'first_name' => '',
                'last_name' => '',
                'full_name' => '',
                'company' => '',
                'email' => '',
                'phone' => '',
                'website' => '',
                'owner_name' => '',
            ];
        }

        if (is_array($recipient)) {
            $firstName = trim((string)($recipient['first_name'] ?? ''));
            $lastName = trim((string)($recipient['last_name'] ?? ''));
            $fullName = trim((string)($recipient['name'] ?? ''));
            if (empty($fullName) && ($firstName || $lastName)) {
                $fullName = trim("{$firstName} {$lastName}");
            }

            return [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => $fullName,
                'company' => trim((string)($recipient['company'] ?? ($recipient['company_name'] ?? ''))),
                'email' => trim((string)($recipient['email'] ?? '')),
                'phone' => trim((string)($recipient['phone'] ?? ($recipient['mobile_phone'] ?? ''))),
                'website' => trim((string)($recipient['website'] ?? ($recipient['company_website'] ?? ''))),
                'owner_name' => trim((string)($recipient['owner_name'] ?? '')),
            ];
        }

        if ($recipient instanceof SalesforceLead) {
            $ownerName = $recipient->owner_name ?: ($recipient->owner ? $recipient->owner->name : '');
            $fullName = $recipient->name ?: trim("{$recipient->first_name} {$recipient->last_name}");

            return [
                'first_name' => trim((string)$recipient->first_name),
                'last_name' => trim((string)$recipient->last_name),
                'full_name' => $fullName,
                'company' => trim((string)$recipient->company),
                'email' => trim((string)$recipient->email),
                'phone' => trim((string)($recipient->phone ?: $recipient->mobile_phone)),
                'website' => trim((string)($recipient->website ?? '')),
                'owner_name' => $ownerName,
            ];
        }

        if ($recipient instanceof SalesforceContact) {
            $ownerName = $recipient->owner_name ?: ($recipient->owner ? $recipient->owner->name : '');
            $company = $recipient->account ? $recipient->account->name : '';
            $website = $recipient->account ? ($recipient->account->website ?? '') : '';
            $fullName = $recipient->name ?: trim("{$recipient->first_name} {$recipient->last_name}");

            return [
                'first_name' => trim((string)$recipient->first_name),
                'last_name' => trim((string)$recipient->last_name),
                'full_name' => $fullName,
                'company' => trim((string)$company),
                'email' => trim((string)$recipient->email),
                'phone' => trim((string)($recipient->phone ?: $recipient->mobile_phone)),
                'website' => trim((string)$website),
                'owner_name' => $ownerName,
            ];
        }

        if ($recipient instanceof SalesforceAccount) {
            $ownerName = $recipient->owner_name ?: ($recipient->owner ? $recipient->owner->name : '');

            return [
                'first_name' => '',
                'last_name' => '',
                'full_name' => trim((string)$recipient->name),
                'company' => trim((string)$recipient->name),
                'email' => '',
                'phone' => trim((string)$recipient->phone),
                'website' => trim((string)($recipient->website ?? '')),
                'owner_name' => $ownerName,
            ];
        }

        if ($recipient instanceof CampaignMember || $recipient instanceof RecipientLog) {
            // Attempt to resolve mapped CRM record if possible
            $email = $recipient->email;
            $crmRecord = app(SalesforceService::class)->getSalesforceRecordByEmail($email);
            if ($crmRecord) {
                return $this->extractRecipientData($crmRecord);
            }

            return [
                'first_name' => '',
                'last_name' => '',
                'full_name' => '',
                'company' => '',
                'email' => $email ?: '',
                'phone' => '',
                'website' => '',
                'owner_name' => '',
            ];
        }

        return [
            'first_name' => '',
            'last_name' => '',
            'full_name' => '',
            'company' => '',
            'email' => '',
            'phone' => '',
            'website' => '',
            'owner_name' => '',
        ];
    }

    /**
     * Resolve merge fields in a given string (subject or body).
     *
     * @param string $content Text or HTML content
     * @param array|Model|null $recipient Recipient data array or model
     * @param User|null $sender User model of the sender
     * @param string|null $signatureHtml Rendered HTML signature
     * @param bool $autoAppendSignature If true and signatureHtml is set, appends signature if {{Signature}} was not present
     * @return string Personalized content
     */
    public function resolve(
        string $content,
        array|Model|null $recipient,
        ?User $sender = null,
        ?string $signatureHtml = null,
        bool $autoAppendSignature = false
    ): string {
        $data = $this->extractRecipientData($recipient);

        $firstName = $data['first_name'];
        $lastName = $data['last_name'];
        $fullName = $data['full_name'];
        $companyName = $data['company'];
        $email = $data['email'];
        $phone = $data['phone'];
        $ownerName = $data['owner_name'];

        $senderName = $sender ? ($sender->name ?: $sender->username) : '';

        // 1. Handle {{FirstName}} with clean punctuation fallback
        if (!empty($firstName)) {
            // Recipient has a first name -> replace directly
            $content = preg_replace('/\{\{\s*FirstName\s*\}\}/i', $firstName, $content);
        } else {
            // Fallback cleanly without leaving awkward trailing spaces or double commas:
            // e.g., "Hi {{FirstName}}," -> "Hi,"
            // e.g., "Hello {{FirstName}}!" -> "Hello!"
            // e.g., "Dear {{FirstName}}:" -> "Dear:"
            $content = preg_replace(
                '/\b(Hi|Hello|Dear|Hey|Greetings)\s+\{\{\s*FirstName\s*\}\}([,!:]?)/i',
                '$1$2',
                $content
            );

            // Any remaining {{FirstName}} preceded by whitespace before punctuation: " {{FirstName}}," -> ","
            $content = preg_replace('/\s+\{\{\s*FirstName\s*\}\}([,!?:;])/i', '$1', $content);

            // Remaining isolated {{FirstName}}: remove cleanly along with single space if present
            $content = preg_replace('/\{\{\s*FirstName\s*\}\}\s?/i', '', $content);
        }

        // 2. Handle {{LastName}}
        if (!empty($lastName)) {
            $content = preg_replace('/\{\{\s*LastName\s*\}\}/i', $lastName, $content);
        } else {
            $content = preg_replace('/\s+\{\{\s*LastName\s*\}\}([,!?:;])/i', '$1', $content);
            $content = preg_replace('/\{\{\s*LastName\s*\}\}\s?/i', '', $content);
        }

        // 3. Handle {{FullName}}
        if (!empty($fullName)) {
            $content = preg_replace('/\{\{\s*FullName\s*\}\}/i', $fullName, $content);
        } else {
            $content = preg_replace('/\s+\{\{\s*FullName\s*\}\}([,!?:;])/i', '$1', $content);
            $content = preg_replace('/\{\{\s*FullName\s*\}\}\s?/i', '', $content);
        }

        // 4. Handle {{CompanyName}}
        if (!empty($companyName)) {
            $content = preg_replace('/\{\{\s*CompanyName\s*\}\}/i', $companyName, $content);
        } else {
            $content = preg_replace('/\{\{\s*CompanyName\s*\}\}\s?/i', '', $content);
        }

        // 5. Handle {{Email}}
        if (!empty($email)) {
            $content = preg_replace('/\{\{\s*Email\s*\}\}/i', $email, $content);
        } else {
            $content = preg_replace('/\{\{\s*Email\s*\}\}\s?/i', '', $content);
        }

        // 6. Handle {{Phone}}
        if (!empty($phone)) {
            $content = preg_replace('/\{\{\s*Phone\s*\}\}/i', $phone, $content);
        } else {
            $content = preg_replace('/\{\{\s*Phone\s*\}\}\s?/i', '', $content);
        }

        // 7. Handle {{OwnerName}}
        if (!empty($ownerName)) {
            $content = preg_replace('/\{\{\s*OwnerName\s*\}\}/i', $ownerName, $content);
        } else {
            $content = preg_replace('/\{\{\s*OwnerName\s*\}\}\s?/i', '', $content);
        }

        // 8. Handle {{SenderName}}
        if (!empty($senderName)) {
            $content = preg_replace('/\{\{\s*SenderName\s*\}\}/i', $senderName, $content);
        } else {
            $content = preg_replace('/\{\{\s*SenderName\s*\}\}\s?/i', '', $content);
        }

        // 9. Handle {{CompanyWebsite}} / {{Website}}
        $website = $data['website'] ?? '';
        if (empty($website) && $sender) {
            $defaultSig = $sender->signatures()->where('is_default', true)->first();
            $website = $defaultSig ? ($defaultSig->website ?? '') : '';
        }
        if (!empty($website)) {
            if (!preg_match('~^(?:f|ht)tps?://~i', $website)) {
                $website = 'https://' . $website;
            }
            $content = preg_replace('/\{\{\s*(?:CompanyWebsite|Website)\s*\}\}/i', $website, $content);
        } else {
            // Replace <a href="{{CompanyWebsite}}"> with <a href="#">
            $content = preg_replace('/(href=["\'])\{\{\s*(?:CompanyWebsite|Website)\s*\}\}(["\'])/i', '$1#$2', $content);
            $content = preg_replace('/\{\{\s*(?:CompanyWebsite|Website)\s*\}\}\s?/i', '', $content);
        }

        // 10. Handle {{CompanyEmail}}
        $companyEmail = '';
        if ($sender) {
            $defaultSig = $sender->signatures()->where('is_default', true)->first();
            $companyEmail = $defaultSig ? ($defaultSig->email ?: $sender->email) : $sender->email;
        }
        if (!empty($companyEmail)) {
            $content = preg_replace('/\{\{\s*CompanyEmail\s*\}\}/i', $companyEmail, $content);
        } else {
            $content = preg_replace('/\{\{\s*CompanyEmail\s*\}\}\s?/i', '', $content);
        }

        // 11. Handle {{CompanyPhone}}
        $companyPhone = '';
        if ($sender) {
            $defaultSig = $sender->signatures()->where('is_default', true)->first();
            $companyPhone = $defaultSig ? ($defaultSig->phone ?? '') : '';
        }
        if (empty($companyPhone) && !empty($phone)) {
            $companyPhone = $phone;
        }
        if (!empty($companyPhone)) {
            $content = preg_replace('/\{\{\s*CompanyPhone\s*\}\}/i', $companyPhone, $content);
        } else {
            $content = preg_replace('/\{\{\s*CompanyPhone\s*\}\}\s?/i', '', $content);
        }

        // 12. Handle {{Signature}}
        $hasSignatureTag = (bool)preg_match('/\{\{\s*Signature\s*\}\}/i', $content);
        if ($hasSignatureTag) {
            $sigReplacement = $signatureHtml ?: '';
            $content = preg_replace('/\{\{\s*Signature\s*\}\}/i', $sigReplacement, $content);
        } elseif ($autoAppendSignature && !empty($signatureHtml)) {
            // Append signature if requested and tag wasn't already in the body
            $content .= "\n" . $signatureHtml;
        }

        // Clean up any double spaces introduced in plain text lines
        $content = preg_replace('/(?<!\S)  +(?!\S)/', ' ', $content);

        return $content;
    }
}
