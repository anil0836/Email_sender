<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSignature extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'sender_name',
        'job_title',
        'company_name',
        'email',
        'phone',
        'website',
        'address',
        'content',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Render the signature as an HTML block for email insertion.
     */
    public function renderHtml(): string
    {
        if (!empty($this->content)) {
            return $this->content;
        }

        $lines = [];
        if (!empty($this->sender_name)) {
            $lines[] = '<div style="font-weight: 600; font-size: 14px; color: #111827;">' . e($this->sender_name) . '</div>';
        }
        $titleCompany = array_filter([$this->job_title, $this->company_name]);
        if (!empty($titleCompany)) {
            $lines[] = '<div style="font-size: 13px; color: #4b5563; margin-top: 2px;">' . e(implode(' | ', $titleCompany)) . '</div>';
        }
        $contactDetails = [];
        if (!empty($this->email)) {
            $contactDetails[] = '<a href="mailto:' . e($this->email) . '" style="color: #2563eb; text-decoration: none;">' . e($this->email) . '</a>';
        }
        if (!empty($this->phone)) {
            $contactDetails[] = '<span>' . e($this->phone) . '</span>';
        }
        if (!empty($this->website)) {
            $url = str_starts_with($this->website, 'http') ? $this->website : 'https://' . $this->website;
            $contactDetails[] = '<a href="' . e($url) . '" target="_blank" style="color: #2563eb; text-decoration: none;">' . e($this->website) . '</a>';
        }
        if (!empty($contactDetails)) {
            $lines[] = '<div style="font-size: 12px; color: #6b7280; margin-top: 6px;">' . implode(' &bull; ', $contactDetails) . '</div>';
        }
        if (!empty($this->address)) {
            $lines[] = '<div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">' . nl2br(e($this->address)) . '</div>';
        }

        return '<div class="email-signature" style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; margin-top: 20px; padding-top: 12px; border-top: 1px solid #e5e7eb; color: #374151; line-height: 1.4;">' . implode('', $lines) . '</div>';
    }
}
