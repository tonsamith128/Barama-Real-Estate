<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\InvoiceTemplateRepository;
use InvalidArgumentException;

final class InvoiceTemplateService
{
    /** @return array<string,mixed> */
    public function get(): array
    {
        return array_merge($this->defaults(), (new InvoiceTemplateRepository())->getDefault());
    }

    /** @param array<string,mixed> $input */
    public function save(array $input): void
    {
        (new InvoiceTemplateRepository())->saveDefault($this->normalize($input));
    }

    public function reset(): void
    {
        $repo = new InvoiceTemplateRepository();
        $repo->resetDefault();
        $repo->saveDefault($this->defaultsForSave());
    }

    /** @return list<string> */
    public function variables(): array
    {
        return [
            '{{invoice_number}}', '{{invoice_title}}', '{{issue_date}}', '{{due_date}}',
            '{{seller_name}}', '{{seller_email}}', '{{seller_phone}}', '{{seller_address}}',
            '{{seller_tax_id}}', '{{customer_name}}', '{{customer_email}}', '{{company_name}}',
            '{{plan_name}}', '{{subtotal}}', '{{discount}}', '{{tax}}', '{{total}}', '{{currency}}',
        ];
    }

    /** @return array<string,mixed> */
    public function defaults(): array
    {
        return array_merge(['template_key' => 'default'], $this->defaultsForSave());
    }

    /** @return array<string,mixed> */
    private function defaultsForSave(): array
    {
        return [
            'template_name' => 'Default Invoice',
            'invoice_title' => 'Invoice',
            'invoice_prefix' => 'INV',
            'number_pattern' => '{{prefix}}-{{year}}-{{sequence4}}',
            'default_due_days' => 14,
            'default_currency' => 'USD',
            'logo_url' => '',
            'accent_color' => '#5F8CFF',
            'seller_name' => 'Barama',
            'seller_email' => '',
            'seller_phone' => '',
            'seller_address' => '',
            'seller_tax_id' => '',
            'customer_label' => 'Bill To',
            'subtotal_label' => 'Subtotal',
            'discount_label' => 'Discount',
            'tax_label' => 'Tax',
            'total_label' => 'Total',
            'payment_instructions' => '',
            'default_notes' => 'Thank you for your business.',
            'footer_text' => 'Barama Real Estate',
            'show_logo' => 1,
            'show_seller_tax_id' => 1,
            'show_payment_instructions' => 1,
        ];
    }

    /** @param array<string,mixed> $input
     *  @return array<string,mixed>
     */
    private function normalize(array $input): array
    {
        $templateName = $this->text($input['template_name'] ?? '', 120);
        $invoiceTitle = $this->text($input['invoice_title'] ?? '', 80);
        $prefix = strtoupper($this->text($input['invoice_prefix'] ?? '', 20));
        $pattern = $this->text($input['number_pattern'] ?? '', 120);
        $currency = strtoupper($this->text($input['default_currency'] ?? '', 3));
        $accent = strtoupper($this->text($input['accent_color'] ?? '', 7));

        if ($templateName === '' || $invoiceTitle === '') {
            throw new InvalidArgumentException('Template name and invoice title are required.');
        }
        if ($prefix === '' || !preg_match('/^[A-Z0-9_-]+$/', $prefix)) {
            throw new InvalidArgumentException('Invoice prefix may contain only letters, numbers, hyphens and underscores.');
        }
        if ($pattern === '') {
            throw new InvalidArgumentException('Invoice number pattern is required.');
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Default currency must be a 3-letter code.');
        }
        if (!preg_match('/^#[0-9A-F]{6}$/', $accent)) {
            throw new InvalidArgumentException('Accent color must be a valid 6-digit hex color.');
        }

        $dueDays = filter_var(
            $input['default_due_days'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 365]]
        );
        if ($dueDays === false) {
            throw new InvalidArgumentException('Default due days must be between 0 and 365.');
        }

        $email = $this->text($input['seller_email'] ?? '', 190);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Seller email is not valid.');
        }

        $logoUrl = $this->text($input['logo_url'] ?? '', 500);
        if ($logoUrl !== '' && filter_var($logoUrl, FILTER_VALIDATE_URL) === false && !str_starts_with($logoUrl, '/')) {
            throw new InvalidArgumentException('Logo URL must be an absolute URL or a site-relative path beginning with /.');
        }

        return [
            'template_name' => $templateName,
            'invoice_title' => $invoiceTitle,
            'invoice_prefix' => $prefix,
            'number_pattern' => $pattern,
            'default_due_days' => (int) $dueDays,
            'default_currency' => $currency,
            'logo_url' => $logoUrl,
            'accent_color' => $accent,
            'seller_name' => $this->text($input['seller_name'] ?? '', 160),
            'seller_email' => $email,
            'seller_phone' => $this->text($input['seller_phone'] ?? '', 80),
            'seller_address' => $this->multiline($input['seller_address'] ?? '', 1000),
            'seller_tax_id' => $this->text($input['seller_tax_id'] ?? '', 120),
            'customer_label' => $this->requiredText($input['customer_label'] ?? '', 60, 'Customer label'),
            'subtotal_label' => $this->requiredText($input['subtotal_label'] ?? '', 60, 'Subtotal label'),
            'discount_label' => $this->requiredText($input['discount_label'] ?? '', 60, 'Discount label'),
            'tax_label' => $this->requiredText($input['tax_label'] ?? '', 60, 'Tax label'),
            'total_label' => $this->requiredText($input['total_label'] ?? '', 60, 'Total label'),
            'payment_instructions' => $this->multiline($input['payment_instructions'] ?? '', 3000),
            'default_notes' => $this->multiline($input['default_notes'] ?? '', 2000),
            'footer_text' => $this->multiline($input['footer_text'] ?? '', 1000),
            'show_logo' => isset($input['show_logo']) ? 1 : 0,
            'show_seller_tax_id' => isset($input['show_seller_tax_id']) ? 1 : 0,
            'show_payment_instructions' => isset($input['show_payment_instructions']) ? 1 : 0,
        ];
    }

    private function requiredText(mixed $value, int $max, string $label): string
    {
        $text = $this->text($value, $max);
        if ($text === '') {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return $text;
    }

    private function text(mixed $value, int $max): string
    {
        $text = trim((string) $value);
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
    }

    private function multiline(mixed $value, int $max): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
    }
}
