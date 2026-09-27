<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class FinanceRepository
{
    /** @return array<int,array<string,mixed>> */
    public function plans(): array
    {
        return Database::connection()
            ->query(
                'SELECT p.*,
                        (SELECT COUNT(*)
                         FROM company_subscriptions s
                         WHERE s.plan_id = p.id
                           AND s.status IN ("active", "paused")) AS current_subscriptions
                 FROM subscription_plans p
                 ORDER BY p.sort_order ASC, p.name ASC'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function activePlans(): array
    {
        return Database::connection()
            ->query(
                'SELECT id, name, slug, billing_interval, price, currency,
                        listing_limit, property_limit, project_limit,
                        team_member_limit, featured_listing_limit
                 FROM subscription_plans
                 WHERE status = "active"
                 ORDER BY sort_order ASC, name ASC'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function companies(): array
    {
        return Database::connection()
            ->query(
                'SELECT c.id, c.name, c.status,
                        (
                            SELECT COUNT(*)
                            FROM company_subscriptions s
                            WHERE s.company_id = c.id
                              AND s.status IN ("active", "paused")
                        ) AS current_subscription_count
                 FROM companies c
                 WHERE c.deleted_at IS NULL
                   AND c.status NOT IN ("archived", "rejected")
                 ORDER BY c.name ASC
                 LIMIT 500'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function subscriptions(): array
    {
        return Database::connection()
            ->query(
                'SELECT s.*, c.name AS company_name, c.status AS company_status,
                        p.name AS plan_name, p.slug AS plan_slug,
                        p.billing_interval, p.price, p.currency
                 FROM company_subscriptions s
                 INNER JOIN companies c ON c.id = s.company_id
                 INNER JOIN subscription_plans p ON p.id = s.plan_id
                 ORDER BY s.updated_at DESC, s.id DESC
                 LIMIT 1000'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function plan(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM subscription_plans WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function subscription(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT s.*, c.name AS company_name, p.name AS plan_name
             FROM company_subscriptions s
             INNER JOIN companies c ON c.id = s.company_id
             INNER JOIN subscription_plans p ON p.id = s.plan_id
             WHERE s.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function planSlugExists(string $slug): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM subscription_plans WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);

        return $statement->fetchColumn() !== false;
    }

    public function companyExists(int $companyId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1
             FROM companies
             WHERE id = :id
               AND deleted_at IS NULL
               AND status NOT IN ("archived", "rejected")
             LIMIT 1'
        );
        $statement->execute(['id' => $companyId]);

        return $statement->fetchColumn() !== false;
    }

    public function lockCompany(int $companyId): void
    {
        $statement = Database::connection()->prepare(
            'SELECT id
             FROM companies
             WHERE id = :id
               AND deleted_at IS NULL
             FOR UPDATE'
        );
        $statement->execute(['id' => $companyId]);

        if ($statement->fetchColumn() === false) {
            throw new \InvalidArgumentException('Company is unavailable for subscription assignment.');
        }
    }

    public function hasCurrentSubscription(int $companyId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1
             FROM company_subscriptions
             WHERE company_id = :company_id
               AND status IN ("active", "paused")
             LIMIT 1'
        );
        $statement->execute(['company_id' => $companyId]);

        return $statement->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $data */
    public function createPlan(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO subscription_plans
                (uuid, name, slug, description, billing_interval, price, currency,
                 listing_limit, property_limit, project_limit, team_member_limit,
                 featured_listing_limit, status, sort_order)
             VALUES
                (:uuid, :name, :slug, :description, :billing_interval, :price, :currency,
                 :listing_limit, :property_limit, :project_limit, :team_member_limit,
                 :featured_listing_limit, "inactive", :sort_order)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'billing_interval' => $data['billing_interval'],
            'price' => $data['price'],
            'currency' => $data['currency'],
            'listing_limit' => $data['listing_limit'],
            'property_limit' => $data['property_limit'],
            'project_limit' => $data['project_limit'],
            'team_member_limit' => $data['team_member_limit'],
            'featured_listing_limit' => $data['featured_listing_limit'],
            'sort_order' => $data['sort_order'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function setPlanStatus(int $id, string $status): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE subscription_plans
             SET status = :status, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute(['status' => $status, 'id' => $id]);
    }

    /** @param array<string,mixed> $data */
    public function createSubscription(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO company_subscriptions
                (uuid, company_id, plan_id, status, source, external_reference,
                 starts_at, renews_at, expires_at, notes,
                 created_by_user_id, updated_by_user_id)
             VALUES
                (:uuid, :company_id, :plan_id, "active", "admin", :external_reference,
                 :starts_at, :renews_at, :expires_at, :notes,
                 :actor_id, :actor_id)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'company_id' => $data['company_id'],
            'plan_id' => $data['plan_id'],
            'external_reference' => $data['external_reference'],
            'starts_at' => $data['starts_at'],
            'renews_at' => $data['renews_at'],
            'expires_at' => $data['expires_at'],
            'notes' => $data['notes'],
            'actor_id' => $data['actor_id'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function setSubscriptionStatus(
        int $id,
        string $status,
        int $actorId
    ): void {
        $statement = Database::connection()->prepare(
            'UPDATE company_subscriptions
             SET status = :status,
                 cancelled_at = CASE
                    WHEN :cancelled_status = "cancelled" THEN CURRENT_TIMESTAMP
                    ELSE cancelled_at
                 END,
                 updated_by_user_id = :actor_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'cancelled_status' => $status,
            'actor_id' => $actorId,
            'id' => $id,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function invoices(): array
    {
        return Database::connection()
            ->query(
                'SELECT i.*, c.name AS company_name,
                        p.name AS plan_name,
                        (SELECT COUNT(*)
                         FROM billing_invoice_items item
                         WHERE item.invoice_id = i.id) AS item_count,
                        (
                            SELECT COALESCE(SUM(
                                CASE
                                    WHEN t.status <> "succeeded" THEN 0
                                    WHEN t.transaction_type IN ("payment", "credit") THEN t.amount
                                    WHEN t.transaction_type = "refund" THEN -t.amount
                                    ELSE 0
                                END
                            ), 0)
                            FROM billing_transactions t
                            WHERE t.invoice_id = i.id
                        ) AS settled_amount
                 FROM billing_invoices i
                 INNER JOIN companies c ON c.id = i.company_id
                 LEFT JOIN company_subscriptions s ON s.id = i.subscription_id
                 LEFT JOIN subscription_plans p ON p.id = s.plan_id
                 ORDER BY i.created_at DESC, i.id DESC
                 LIMIT 1000'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function transactions(): array
    {
        return Database::connection()
            ->query(
                'SELECT t.*, c.name AS company_name,
                        i.invoice_number,
                        p.name AS plan_name
                 FROM billing_transactions t
                 INNER JOIN companies c ON c.id = t.company_id
                 LEFT JOIN billing_invoices i ON i.id = t.invoice_id
                 LEFT JOIN company_subscriptions s ON s.id = t.subscription_id
                 LEFT JOIN subscription_plans p ON p.id = s.plan_id
                 ORDER BY t.processed_at DESC, t.id DESC
                 LIMIT 1500'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function invoice(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT i.*, c.name AS company_name,
                    p.name AS plan_name
             FROM billing_invoices i
             INNER JOIN companies c ON c.id = i.company_id
             LEFT JOIN company_subscriptions s ON s.id = i.subscription_id
             LEFT JOIN subscription_plans p ON p.id = s.plan_id
             WHERE i.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function subscriptionBelongsToCompany(
        int $subscriptionId,
        int $companyId
    ): bool {
        $statement = Database::connection()->prepare(
            'SELECT 1
             FROM company_subscriptions
             WHERE id = :id
               AND company_id = :company_id
             LIMIT 1'
        );
        $statement->execute([
            'id' => $subscriptionId,
            'company_id' => $companyId,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $data */
    public function createInvoice(array $data): int
    {
        $uuid = IdGenerator::uuid();
        $invoiceNumber = 'INV-' . strtoupper(substr(str_replace('-', '', $uuid), 0, 16));

        $statement = Database::connection()->prepare(
            'INSERT INTO billing_invoices
                (uuid, invoice_number, company_id, subscription_id, status,
                 currency, subtotal, total, due_at, notes,
                 created_by_user_id, updated_by_user_id)
             VALUES
                (:uuid, :invoice_number, :company_id, :subscription_id, "draft",
                 :currency, :subtotal, :total, :due_at, :notes,
                 :actor_id, :actor_id)'
        );
        $statement->execute([
            'uuid' => $uuid,
            'invoice_number' => $invoiceNumber,
            'company_id' => $data['company_id'],
            'subscription_id' => $data['subscription_id'],
            'currency' => $data['currency'],
            'subtotal' => $data['subtotal'],
            'total' => $data['total'],
            'due_at' => $data['due_at'],
            'notes' => $data['notes'],
            'actor_id' => $data['actor_id'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function createInvoiceItem(int $invoiceId, array $data): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO billing_invoice_items
                (invoice_id, description, quantity, unit_amount, line_total)
             VALUES
                (:invoice_id, :description, :quantity, :unit_amount, :line_total)'
        );
        $statement->execute([
            'invoice_id' => $invoiceId,
            'description' => $data['description'],
            'quantity' => $data['quantity'],
            'unit_amount' => $data['unit_amount'],
            'line_total' => $data['line_total'],
        ]);
    }

    public function setInvoiceStatus(
        int $id,
        string $status,
        int $actorId
    ): void {
        $statement = Database::connection()->prepare(
            'UPDATE billing_invoices
             SET status = :status,
                 issued_at = CASE
                    WHEN :issued_status = "open" AND issued_at IS NULL
                        THEN CURRENT_TIMESTAMP
                    ELSE issued_at
                 END,
                 paid_at = CASE
                    WHEN :paid_status = "paid" THEN CURRENT_TIMESTAMP
                    ELSE paid_at
                 END,
                 updated_by_user_id = :actor_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'issued_status' => $status,
            'paid_status' => $status,
            'actor_id' => $actorId,
            'id' => $id,
        ]);
    }

    public function invoiceSettledAmount(int $invoiceId): float
    {
        $statement = Database::connection()->prepare(
            'SELECT COALESCE(SUM(
                CASE
                    WHEN status <> "succeeded" THEN 0
                    WHEN transaction_type IN ("payment", "credit") THEN amount
                    WHEN transaction_type = "refund" THEN -amount
                    ELSE 0
                END
             ), 0)
             FROM billing_transactions
             WHERE invoice_id = :invoice_id'
        );
        $statement->execute(['invoice_id' => $invoiceId]);

        return (float) $statement->fetchColumn();
    }

    /** @param array<string,mixed> $data */
    public function createTransaction(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO billing_transactions
                (uuid, company_id, subscription_id, invoice_id,
                 transaction_type, status, source, reference,
                 amount, currency, processed_at, notes, created_by_user_id)
             VALUES
                (:uuid, :company_id, :subscription_id, :invoice_id,
                 :transaction_type, :status, "admin", :reference,
                 :amount, :currency, :processed_at, :notes, :actor_id)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'company_id' => $data['company_id'],
            'subscription_id' => $data['subscription_id'],
            'invoice_id' => $data['invoice_id'],
            'transaction_type' => $data['transaction_type'],
            'status' => $data['status'],
            'reference' => $data['reference'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'processed_at' => $data['processed_at'],
            'notes' => $data['notes'],
            'actor_id' => $data['actor_id'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @return array<string,mixed> */
    public function ledgerSummary(): array
    {
        $row = Database::connection()->query(
            'SELECT
                (SELECT COUNT(*) FROM billing_invoices) AS invoices,
                (SELECT COUNT(*) FROM billing_invoices WHERE status = "draft") AS draft_invoices,
                (SELECT COUNT(*) FROM billing_invoices WHERE status = "open") AS open_invoices,
                (SELECT COUNT(*) FROM billing_invoices WHERE status = "paid") AS paid_invoices,
                (SELECT COUNT(*) FROM billing_transactions) AS transactions,
                (SELECT COUNT(*) FROM billing_transactions WHERE status = "succeeded") AS succeeded_transactions,
                (SELECT COUNT(*) FROM billing_transactions WHERE status = "pending") AS pending_transactions,
                (SELECT COUNT(*) FROM billing_transactions WHERE status = "failed") AS failed_transactions
            '
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        $row = Database::connection()->query(
            'SELECT
                (SELECT COUNT(*) FROM subscription_plans) AS plans,
                (SELECT COUNT(*) FROM subscription_plans WHERE status = "active") AS active_plans,
                (SELECT COUNT(*) FROM company_subscriptions WHERE status = "active") AS active_subscriptions,
                (SELECT COUNT(*) FROM company_subscriptions WHERE status = "paused") AS paused_subscriptions,
                (SELECT COUNT(*) FROM company_subscriptions WHERE status IN ("cancelled", "expired")) AS ended_subscriptions'
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row)
            ? array_map(static fn (mixed $value): int => (int) $value, $row)
            : [];
    }
}
