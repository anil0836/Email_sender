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
        'layout_style',
        'content',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'rendered_html',
        'single_line_html',
        'single_line_bar_html',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Accessor for default rendered html based on layout_style.
     */
    public function getRenderedHtmlAttribute(): string
    {
        return $this->renderHtml();
    }

    /**
     * Accessor for single-line rendered html.
     */
    public function getSingleLineHtmlAttribute(): string
    {
        return $this->renderSingleLineHtml();
    }

    /**
     * Accessor for single-line accent bar rendered html.
     */
    public function getSingleLineBarHtmlAttribute(): string
    {
        return $this->renderSingleLineBarHtml();
    }

    /**
     * Render the signature as an HTML block for email insertion based on layout style.
     */
    public function renderHtml(?string $style = null): string
    {
        $targetStyle = $style ?: ($this->layout_style ?: 'standard');

        if ($targetStyle === 'single_line') {
            return $this->renderSingleLineHtml();
        }

        if ($targetStyle === 'single_line_bar') {
            return $this->renderSingleLineBarHtml();
        }

        return $this->renderStandardHtml();
    }

    /**
     * Render as a sleek horizontal single-line inline layout.
     */
    public function renderSingleLineHtml(): string
    {
        $items = $this->buildSingleLineItems();

        if (empty($items)) {
            return !empty($this->content) ? $this->content : '';
        }

        $sep = '<span class="sig-sep" style="color: #cbd5e1; margin: 0 7px; font-weight: 300;">|</span>';
        $joined = implode($sep, $items);

        return '<div class="email-signature-single-line" style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 13px; line-height: 1.6; color: #475569; margin-top: 14px; padding-top: 10px; border-top: 1px solid #e2e8f0;">' . $joined . '</div>';
    }

    /**
     * Render as a horizontal single-line layout inside a styled accent-bar container.
     */
    public function renderSingleLineBarHtml(): string
    {
        $items = $this->buildSingleLineItems();

        if (empty($items)) {
            return !empty($this->content) ? $this->content : '';
        }

        $sep = '<span class="sig-sep" style="color: #cbd5e1; margin: 0 7px; font-weight: 300;">|</span>';
        $joined = implode($sep, $items);

        return '<div class="email-signature-single-line-bar" style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 13px; line-height: 1.6; color: #475569; margin-top: 14px; padding: 10px 14px; background-color: #f8fafc; border-left: 3px solid #4f46e5; border-radius: 4px;">' . $joined . '</div>';
    }

    /**
     * Build the structured inline segments for single-line rendering.
     */
    protected function buildSingleLineItems(): array
    {
        $items = [];

        // 1. Sender Name (Bold)
        if (!empty($this->sender_name)) {
            $items[] = '<span style="font-weight: 700; color: #0f172a; letter-spacing: -0.01em;">' . e($this->sender_name) . '</span>';
        }

        // 2. Job Title and Company Name
        $hasTitle = !empty($this->job_title);
        $hasCompany = !empty($this->company_name);

        if ($hasTitle && $hasCompany) {
            $items[] = '<span style="color: #334155; font-weight: 500;">' . e($this->job_title) . '</span> <span style="color: #64748b;">at</span> <span style="font-weight: 600; color: #0f172a;">' . e($this->company_name) . '</span>';
        } elseif ($hasTitle) {
            $items[] = '<span style="color: #334155; font-weight: 500;">' . e($this->job_title) . '</span>';
        } elseif ($hasCompany) {
            $items[] = '<span style="font-weight: 600; color: #0f172a;">' . e($this->company_name) . '</span>';
        }

        // 3. Email Address
        if (!empty($this->email)) {
            $items[] = '<a href="mailto:' . e($this->email) . '" style="color: #2563eb; text-decoration: none; font-weight: 500;">' . e($this->email) . '</a>';
        }

        // 4. Phone Number
        if (!empty($this->phone)) {
            $phoneClean = preg_replace('/[^0-9+]/', '', $this->phone);
            $items[] = '<a href="tel:' . e($phoneClean) . '" style="color: #475569; text-decoration: none;">' . e($this->phone) . '</a>';
        }

        // 5. Website URL
        if (!empty($this->website)) {
            $url = str_starts_with($this->website, 'http') ? $this->website : 'https://' . $this->website;
            $displayUrl = preg_replace('~^https?://(?:www\.)?~i', '', rtrim($this->website, '/'));
            $items[] = '<a href="' . e($url) . '" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 500;">' . e($displayUrl) . '</a>';
        }

        // 6. Address (flattened to a single line)
        if (!empty($this->address)) {
            $flatAddress = preg_replace('/\s+/', ' ', str_replace(["\r\n", "\r", "\n"], ', ', trim($this->address)));
            $items[] = '<span style="color: #64748b; font-size: 12px;">' . e($flatAddress) . '</span>';
        }

        return $items;
    }

    /**
     * Render as the standard multi-line stacked layout.
     */
    public function renderStandardHtml(): string
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

        if (empty($lines)) {
            return '';
        }

        return '<div class="email-signature" style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; margin-top: 20px; padding-top: 12px; border-top: 1px solid #e5e7eb; color: #374151; line-height: 1.4;">' . implode('', $lines) . '</div>';
    }
}
