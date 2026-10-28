<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDOException;

final class InvoiceTemplateRepository
{
    /** @return array<string,mixed> */
    public function getDefault(): array
    {
        $pdo = Database::connection();
        try {
            $statement = $pdo->prepare(
                'SELECT * FROM invoice_templates WHERE template_key = :template_key LIMIT 1'
            );
            $statement->execute(['template_key' => 'default']);
            $row = $statement->fetch(\PDO::FETCH_ASSOC);

            return is_array($row) ? $row : [];
        } catch (PDOException $exception) {
            // Older v1.0.0 customer installs lacked the invoice-template
            // migration. Keep the Admin layout intact without issuing DDL
            // from a GET request; the authenticated updater applies 0032.
            if ((string) $exception->getCode() === '42S02') {
                return ['__migration_required' => true];
            }

            throw $exception;
        }
    }

    /** @param array<string,mixed> $data */
    public function saveDefault(array $data): void
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'INSERT INTO invoice_templates (
                template_key, template_name, invoice_title, invoice_prefix,
                number_pattern, default_due_days, default_currency, logo_url,
                accent_color, seller_name, seller_email, seller_phone,
                seller_address, seller_tax_id, customer_label, subtotal_label,
                discount_label, tax_label, total_label, payment_instructions,
                default_notes, footer_text, show_logo, show_seller_tax_id,
                show_payment_instructions
             ) VALUES (
                :template_key, :template_name, :invoice_title, :invoice_prefix,
                :number_pattern, :default_due_days, :default_currency, :logo_url,
                :accent_color, :seller_name, :seller_email, :seller_phone,
                :seller_address, :seller_tax_id, :customer_label, :subtotal_label,
                :discount_label, :tax_label, :total_label, :payment_instructions,
                :default_notes, :footer_text, :show_logo, :show_seller_tax_id,
                :show_payment_instructions
             )
             ON DUPLICATE KEY UPDATE
                template_name = VALUES(template_name),
                invoice_title = VALUES(invoice_title),
                invoice_prefix = VALUES(invoice_prefix),
                number_pattern = VALUES(number_pattern),
                default_due_days = VALUES(default_due_days),
                default_currency = VALUES(default_currency),
                logo_url = VALUES(logo_url),
                accent_color = VALUES(accent_color),
                seller_name = VALUES(seller_name),
                seller_email = VALUES(seller_email),
                seller_phone = VALUES(seller_phone),
                seller_address = VALUES(seller_address),
                seller_tax_id = VALUES(seller_tax_id),
                customer_label = VALUES(customer_label),
                subtotal_label = VALUES(subtotal_label),
                discount_label = VALUES(discount_label),
                tax_label = VALUES(tax_label),
                total_label = VALUES(total_label),
                payment_instructions = VALUES(payment_instructions),
                default_notes = VALUES(default_notes),
                footer_text = VALUES(footer_text),
                show_logo = VALUES(show_logo),
                show_seller_tax_id = VALUES(show_seller_tax_id),
                show_payment_instructions = VALUES(show_payment_instructions),
                updated_at = CURRENT_TIMESTAMP'
        );

        $statement->execute(array_merge(['template_key' => 'default'], $data));
    }

    public function resetDefault(): void
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'DELETE FROM invoice_templates WHERE template_key = :template_key'
        );
        $statement->execute(['template_key' => 'default']);
    }
}
