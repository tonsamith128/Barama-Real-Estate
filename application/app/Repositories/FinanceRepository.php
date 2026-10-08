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

    /** @return array<string,mixed>|null */
    public function companyEntitlementSubscription(
        int $companyId,
        bool $forUpdate = false
    ): ?array {
        $sql = 'SELECT s.*, p.name AS plan_name, p.slug AS plan_slug,
                       p.billing_interval, p.price, p.currency,
                       p.listing_limit, p.property_limit, p.project_limit,
                       p.team_member_limit, p.featured_listing_limit
                FROM company_subscriptions s
                INNER JOIN subscription_plans p ON p.id = s.plan_id
                WHERE s.company_id = :company_id
                ORDER BY
                    CASE
                        WHEN s.status = "active" THEN 0
                        WHEN s.status = "paused" THEN 1
                        ELSE 2
                    END,
                    s.updated_at DESC,
                    s.id DESC
                LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute(['company_id' => $companyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,int> */
    public function companyEntitlementUsage(int $companyId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                (
                    SELECT COUNT(*)
                    FROM projects p
                    WHERE p.company_id = :company_id1
                      AND p.deleted_at IS NULL
                      AND p.status <> "archived"
                ) AS projects,
                (
                    SELECT COUNT(*)
                    FROM properties p
                    WHERE p.company_id = :company_id2
                      AND p.deleted_at IS NULL
                      AND p.status <> "archived"
                ) AS properties,
                (
                    SELECT COUNT(*)
                    FROM listings l
                    INNER JOIN properties p ON p.id = l.property_id
                    WHERE p.company_id = :company_id3
                      AND p.deleted_at IS NULL
                      AND l.deleted_at IS NULL
                      AND l.status NOT IN ("closed", "expired")
                ) AS listings,
                (
                    SELECT COUNT(*)
                    FROM company_members cm
                    WHERE cm.company_id = :company_id4
                      AND cm.status = "active"
                ) AS active_team_members,
                (
                    SELECT COUNT(*)
                    FROM company_invitations ci
                    WHERE ci.company_id = :company_id5
                      AND ci.status = "pending"
                      AND ci.expires_at > CURRENT_TIMESTAMP
                ) AS pending_team_invitations'
        );
        $statement->execute([
            'company_id1' => $companyId,
            'company_id2' => $companyId,
            'company_id3' => $companyId,
            'company_id4' => $companyId,
            'company_id5' => $companyId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return [
                'projects' => 0,
                'properties' => 0,
                'listings' => 0,
                'active_team_members' => 0,
                'pending_team_invitations' => 0,
                'team_seats' => 0,
            ];
        }

        $usage = array_map(
            static fn (mixed $value): int => (int) ($value ?? 0),
            $row
        );
        $usage['team_seats'] = $usage['active_team_members']
            + $usage['pending_team_invitations'];

        return $usage;
    }

    /** @return array<int,array<string,mixed>> */
    public function developerBillingCompanies(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.id, c.name, c.slug, c.status AS company_status,
                    cm.member_role
             FROM companies c
             INNER JOIN company_members cm ON cm.company_id = c.id
             WHERE cm.user_id = :user_id
               AND cm.status = "active"
               AND c.deleted_at IS NULL
             ORDER BY c.name ASC'
        );
        $statement->execute(['user_id' => $userId]);

        $companies = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $company) {
            $companyId = (int) $company['id'];
            $company['subscription'] = $this->companyEntitlementSubscription(
                $companyId
            );
            $company['usage'] = $this->companyEntitlementUsage($companyId);
            $companies[] = $company;
        }

        return $companies;
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

    /** @param array<string,mixed> $data */
    public function updatePlan(int $id, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE subscription_plans
             SET name = :name,
                 slug = :slug,
                 description = :description,
                 billing_interval = :billing_interval,
                 price = :price,
                 currency = :currency,
                 listing_limit = :listing_limit,
                 property_limit = :property_limit,
                 project_limit = :project_limit,
                 team_member_limit = :team_member_limit,
                 featured_listing_limit = :featured_listing_limit,
                 sort_order = :sort_order,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $statement->execute([
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
            'id' => $id,
        ]);
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
                        coupon.code AS coupon_code,
                        tax.code AS tax_rule_code,
                        tax.name AS tax_rule_name,
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
                 LEFT JOIN billing_coupons coupon ON coupon.id = i.coupon_id
                 LEFT JOIN billing_tax_rules tax ON tax.id = i.tax_rule_id
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
                    p.name AS plan_name,
                    coupon.code AS coupon_code,
                    tax.code AS tax_rule_code,
                    tax.name AS tax_rule_name
             FROM billing_invoices i
             INNER JOIN companies c ON c.id = i.company_id
             LEFT JOIN company_subscriptions s ON s.id = i.subscription_id
             LEFT JOIN subscription_plans p ON p.id = s.plan_id
             LEFT JOIN billing_coupons coupon ON coupon.id = i.coupon_id
             LEFT JOIN billing_tax_rules tax ON tax.id = i.tax_rule_id
             WHERE i.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function invoiceByNumber(string $invoiceNumber): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT i.*, c.name AS company_name
             FROM billing_invoices i
             INNER JOIN companies c ON c.id = i.company_id
             WHERE i.invoice_number = :invoice_number
             LIMIT 1'
        );
        $statement->execute(['invoice_number' => $invoiceNumber]);
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
                (uuid, invoice_number, company_id, subscription_id, coupon_id, tax_rule_id, status,
                 currency, subtotal, discount_total, taxable_total, tax_rate, tax_total,
                 total, due_at, notes, created_by_user_id, updated_by_user_id)
             VALUES
                (:uuid, :invoice_number, :company_id, :subscription_id, :coupon_id, :tax_rule_id, "draft",
                 :currency, :subtotal, :discount_total, :taxable_total, :tax_rate, :tax_total,
                 :total, :due_at, :notes, :actor_id, :actor_id)'
        );
        $statement->execute([
            'uuid' => $uuid,
            'invoice_number' => $invoiceNumber,
            'company_id' => $data['company_id'],
            'subscription_id' => $data['subscription_id'],
            'coupon_id' => $data['coupon_id'],
            'tax_rule_id' => $data['tax_rule_id'],
            'currency' => $data['currency'],
            'subtotal' => $data['subtotal'],
            'discount_total' => $data['discount_total'],
            'taxable_total' => $data['taxable_total'],
            'tax_rate' => $data['tax_rate'],
            'tax_total' => $data['tax_total'],
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
                 :transaction_type, :status, :source, :reference,
                 :amount, :currency, :processed_at, :notes, :actor_id)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'company_id' => $data['company_id'],
            'subscription_id' => $data['subscription_id'],
            'invoice_id' => $data['invoice_id'],
            'transaction_type' => $data['transaction_type'],
            'status' => $data['status'],
            'source' => $data['source'] ?? 'admin',
            'reference' => $data['reference'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'processed_at' => $data['processed_at'],
            'notes' => $data['notes'],
            'actor_id' => $data['actor_id'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @return array<int,array<string,mixed>> */
    public function coupons(): array
    {
        return Database::connection()
            ->query(
                'SELECT c.*,
                        (
                            SELECT COUNT(*)
                            FROM billing_coupon_redemptions r
                            INNER JOIN billing_invoices i ON i.id = r.invoice_id
                            WHERE r.coupon_id = c.id
                              AND i.status <> "void"
                        ) AS effective_redemptions
                 FROM billing_coupons c
                 ORDER BY c.created_at DESC, c.id DESC
                 LIMIT 1000'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function coupon(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM billing_coupons WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function lockCouponByCode(string $code): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM billing_coupons
             WHERE code = :code
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute(['code' => $code]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function couponCodeExists(string $code): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM billing_coupons WHERE code = :code LIMIT 1'
        );
        $statement->execute(['code' => $code]);

        return $statement->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $data */
    public function createCoupon(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO billing_coupons
                (uuid, code, name, description, discount_type, discount_value,
                 currency, max_redemptions, per_company_limit,
                 starts_at, ends_at, status,
                 created_by_user_id, updated_by_user_id)
             VALUES
                (:uuid, :code, :name, :description, :discount_type, :discount_value,
                 :currency, :max_redemptions, :per_company_limit,
                 :starts_at, :ends_at, "inactive",
                 :actor_id, :actor_id)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'],
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'currency' => $data['currency'],
            'max_redemptions' => $data['max_redemptions'],
            'per_company_limit' => $data['per_company_limit'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'actor_id' => $data['actor_id'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function setCouponStatus(int $id, string $status, int $actorId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE billing_coupons
             SET status = :status,
                 updated_by_user_id = :actor_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'actor_id' => $actorId,
            'id' => $id,
        ]);
    }

    public function effectiveCouponRedemptions(
        int $couponId,
        ?int $companyId = null
    ): int {
        $sql =
            'SELECT COUNT(*)
             FROM billing_coupon_redemptions r
             INNER JOIN billing_invoices i ON i.id = r.invoice_id
             WHERE r.coupon_id = :coupon_id
               AND i.status <> "void"';

        $params = ['coupon_id' => $couponId];

        if ($companyId !== null) {
            $sql .= ' AND r.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function createCouponRedemption(
        int $couponId,
        int $invoiceId,
        int $companyId,
        string $discountAmount,
        string $currency,
        int $actorId
    ): void {
        $statement = Database::connection()->prepare(
            'INSERT INTO billing_coupon_redemptions
                (coupon_id, invoice_id, company_id,
                 discount_amount, currency, created_by_user_id)
             VALUES
                (:coupon_id, :invoice_id, :company_id,
                 :discount_amount, :currency, :actor_id)'
        );
        $statement->execute([
            'coupon_id' => $couponId,
            'invoice_id' => $invoiceId,
            'company_id' => $companyId,
            'discount_amount' => $discountAmount,
            'currency' => $currency,
            'actor_id' => $actorId,
        ]);
    }

    /** @return array<string,int> */
    public function couponSummary(): array
    {
        $row = Database::connection()->query(
            'SELECT
                (SELECT COUNT(*) FROM billing_coupons) AS coupons,
                (SELECT COUNT(*) FROM billing_coupons WHERE status = "active") AS active_coupons,
                (
                    SELECT COUNT(*)
                    FROM billing_coupon_redemptions r
                    INNER JOIN billing_invoices i ON i.id = r.invoice_id
                    WHERE i.status <> "void"
                ) AS effective_redemptions'
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row)
            ? array_map(static fn (mixed $value): int => (int) $value, $row)
            : [];
    }

    /** @return array<int,array<string,mixed>> */
    public function taxRules(): array
    {
        return Database::connection()
            ->query(
                'SELECT *
                 FROM billing_tax_rules
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1000'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function taxRule(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM billing_tax_rules WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function lockTaxRuleByCode(string $code): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM billing_tax_rules
             WHERE code = :code
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute(['code' => $code]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function taxRuleCodeExists(string $code): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM billing_tax_rules WHERE code = :code LIMIT 1'
        );
        $statement->execute(['code' => $code]);

        return $statement->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $data */
    public function createTaxRule(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO billing_tax_rules
                (uuid, code, name, description, rate_percent,
                 country_code, region_code, starts_at, ends_at, status,
                 created_by_user_id, updated_by_user_id)
             VALUES
                (:uuid, :code, :name, :description, :rate_percent,
                 :country_code, :region_code, :starts_at, :ends_at, "inactive",
                 :actor_id, :actor_id)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'],
            'rate_percent' => $data['rate_percent'],
            'country_code' => $data['country_code'],
            'region_code' => $data['region_code'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'actor_id' => $data['actor_id'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function setTaxRuleStatus(int $id, string $status, int $actorId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE billing_tax_rules
             SET status = :status,
                 updated_by_user_id = :actor_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'actor_id' => $actorId,
            'id' => $id,
        ]);
    }

    /** @return array<string,int> */
    public function taxSummary(): array
    {
        $row = Database::connection()->query(
            'SELECT
                (SELECT COUNT(*) FROM billing_tax_rules) AS tax_rules,
                (SELECT COUNT(*) FROM billing_tax_rules WHERE status = "active") AS active_tax_rules,
                (SELECT COUNT(*) FROM billing_invoices WHERE tax_rule_id IS NOT NULL) AS taxed_invoices'
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row)
            ? array_map(static fn (mixed $value): int => (int) $value, $row)
            : [];
    }

    /** @return array<int,array<string,mixed>> */
    public function paymentProviders(): array
    {
        return Database::connection()
            ->query(
                'SELECT p.*,
                        (
                            SELECT COUNT(*)
                            FROM billing_webhook_events e
                            WHERE e.provider_id = p.id
                        ) AS webhook_events,
                        (
                            SELECT COUNT(*)
                            FROM billing_webhook_events e
                            WHERE e.provider_id = p.id
                              AND e.status = "failed"
                        ) AS failed_events
                 FROM billing_payment_providers p
                 ORDER BY p.created_at DESC, p.id DESC
                 LIMIT 500'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function paymentProvider(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM billing_payment_providers WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function paymentProviderByCode(string $code): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM billing_payment_providers WHERE code = :code LIMIT 1'
        );
        $statement->execute(['code' => $code]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function paymentProviderCodeExists(string $code): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM billing_payment_providers WHERE code = :code LIMIT 1'
        );
        $statement->execute(['code' => $code]);

        return $statement->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $data */
    public function createPaymentProvider(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO billing_payment_providers
                (uuid, code, name, adapter_key, environment,
                 webhook_token_hash, status,
                 created_by_user_id, updated_by_user_id)
             VALUES
                (:uuid, :code, :name, :adapter_key, :environment,
                 :webhook_token_hash, "inactive",
                 :actor_id, :actor_id)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'code' => $data['code'],
            'name' => $data['name'],
            'adapter_key' => $data['adapter_key'],
            'environment' => $data['environment'],
            'webhook_token_hash' => $data['webhook_token_hash'],
            'actor_id' => $data['actor_id'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /* BARAMA_PAYMENT_PROVIDERS_PREMIUM_V100 */
    /** @param array<string,mixed> $data */
    public function updatePaymentProvider(int $id, array $data): void
    {
        $tokenHash = $data['webhook_token_hash'] ?? null;

        if (is_string($tokenHash) && $tokenHash !== '') {
            $statement = Database::connection()->prepare(
                'UPDATE billing_payment_providers
                 SET name = :name,
                     environment = :environment,
                     webhook_token_hash = :webhook_token_hash,
                     updated_by_user_id = :actor_id,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $statement->execute([
                'name' => $data['name'],
                'environment' => $data['environment'],
                'webhook_token_hash' => $tokenHash,
                'actor_id' => $data['actor_id'],
                'id' => $id,
            ]);
            return;
        }

        $statement = Database::connection()->prepare(
            'UPDATE billing_payment_providers
             SET name = :name,
                 environment = :environment,
                 updated_by_user_id = :actor_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'name' => $data['name'],
            'environment' => $data['environment'],
            'actor_id' => $data['actor_id'],
            'id' => $id,
        ]);
    }

    public function setPaymentProviderStatus(
        int $id,
        string $status,
        int $actorId
    ): void {
        $statement = Database::connection()->prepare(
            'UPDATE billing_payment_providers
             SET status = :status,
                 updated_by_user_id = :actor_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'actor_id' => $actorId,
            'id' => $id,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function webhookEvents(): array
    {
        return Database::connection()
            ->query(
                'SELECT e.*, p.code AS provider_code, p.name AS provider_name,
                        t.reference AS transaction_reference
                 FROM billing_webhook_events e
                 INNER JOIN billing_payment_providers p ON p.id = e.provider_id
                 LEFT JOIN billing_transactions t ON t.id = e.transaction_id
                 ORDER BY e.received_at DESC, e.id DESC
                 LIMIT 1500'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function webhookEvent(int $providerId, string $eventId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM billing_webhook_events
             WHERE provider_id = :provider_id
               AND event_id = :event_id
             LIMIT 1'
        );
        $statement->execute([
            'provider_id' => $providerId,
            'event_id' => $eventId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function lockWebhookEvent(int $providerId, string $eventId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM billing_webhook_events
             WHERE provider_id = :provider_id
               AND event_id = :event_id
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute([
            'provider_id' => $providerId,
            'event_id' => $eventId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function lockWebhookEventById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT e.*, p.code AS provider_code, p.name AS provider_name,
                    p.status AS provider_status, p.adapter_key, p.environment
             FROM billing_webhook_events e
             INNER JOIN billing_payment_providers p ON p.id = e.provider_id
             WHERE e.id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $data */
    public function createWebhookEvent(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO billing_webhook_events
                (uuid, provider_id, event_id, event_type, status,
                 payload_json, payload_sha256, received_at)
             VALUES
                (:uuid, :provider_id, :event_id, :event_type, "received",
                 :payload_json, :payload_sha256, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'provider_id' => $data['provider_id'],
            'event_id' => $data['event_id'],
            'event_type' => $data['event_type'],
            'payload_json' => $data['payload_json'],
            'payload_sha256' => $data['payload_sha256'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function completeWebhookEvent(
        int $id,
        string $status,
        ?int $transactionId = null,
        ?string $errorMessage = null
    ): void {
        $statement = Database::connection()->prepare(
            'UPDATE billing_webhook_events
             SET status = :status,
                 transaction_id = :transaction_id,
                 error_message = :error_message,
                 processed_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'transaction_id' => $transactionId,
            'error_message' => $errorMessage,
            'id' => $id,
        ]);
    }

    /** @return array<string,int> */
    public function paymentProviderSummary(): array
    {
        $row = Database::connection()->query(
            'SELECT
                (SELECT COUNT(*) FROM billing_payment_providers) AS providers,
                (SELECT COUNT(*) FROM billing_payment_providers WHERE status = "active") AS active_providers,
                (SELECT COUNT(*) FROM billing_webhook_events) AS webhook_events,
                (SELECT COUNT(*) FROM billing_webhook_events WHERE status = "failed") AS failed_events'
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row)
            ? array_map(static fn (mixed $value): int => (int) $value, $row)
            : [];
    }

    /** @return array<int,array<string,mixed>> */
    public function reconciliationInvoices(): array
    {
        return Database::connection()
            ->query(
                'SELECT x.*,
                        ROUND(x.total - x.settled_amount, 2) AS settlement_delta,
                        CASE
                            WHEN x.settled_amount > x.total + 0.005 THEN "overpaid"
                            WHEN x.status = "paid" AND x.settled_amount < x.total - 0.005 THEN "paid_mismatch"
                            WHEN x.status = "open"
                                 AND x.due_at IS NOT NULL
                                 AND x.due_at < CURRENT_TIMESTAMP
                                 AND x.settled_amount < x.total - 0.005 THEN "overdue"
                            WHEN x.status = "open"
                                 AND x.settled_amount > 0.005
                                 AND x.settled_amount < x.total - 0.005 THEN "partial"
                            WHEN x.status = "open"
                                 AND x.settled_amount <= 0.005 THEN "unpaid"
                            WHEN ABS(x.settled_amount - x.total) <= 0.005 THEN "balanced"
                            WHEN x.status IN ("draft", "void")
                                 AND ABS(x.settled_amount) > 0.005 THEN "unexpected_settlement"
                            ELSE "review"
                        END AS reconciliation_state
                 FROM (
                    SELECT i.*, c.name AS company_name,
                           COALESCE((
                               SELECT SUM(
                                   CASE
                                       WHEN t.status <> "succeeded" THEN 0
                                       WHEN t.transaction_type IN ("payment", "credit") THEN t.amount
                                       WHEN t.transaction_type = "refund" THEN -t.amount
                                       ELSE 0
                                   END
                               )
                               FROM billing_transactions t
                               WHERE t.invoice_id = i.id
                           ), 0) AS settled_amount
                    FROM billing_invoices i
                    INNER JOIN companies c ON c.id = i.company_id
                 ) x
                 WHERE x.status IN ("open", "paid")
                    OR ABS(x.settled_amount) > 0.005
                 ORDER BY
                    CASE
                        WHEN x.settled_amount > x.total + 0.005 THEN 0
                        WHEN x.status = "paid" AND x.settled_amount < x.total - 0.005 THEN 1
                        WHEN x.status = "open"
                             AND x.due_at IS NOT NULL
                             AND x.due_at < CURRENT_TIMESTAMP
                             AND x.settled_amount < x.total - 0.005 THEN 2
                        WHEN x.status IN ("draft", "void")
                             AND ABS(x.settled_amount) > 0.005 THEN 3
                        ELSE 4
                    END,
                    x.due_at ASC,
                    x.created_at DESC
                 LIMIT 1000'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function webhookOperationalEvents(): array
    {
        return Database::connection()
            ->query(
                'SELECT e.*, p.code AS provider_code, p.name AS provider_name,
                        p.status AS provider_status
                 FROM billing_webhook_events e
                 INNER JOIN billing_payment_providers p ON p.id = e.provider_id
                 WHERE e.status = "failed"
                    OR (
                        e.status = "received"
                        AND e.received_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 10 MINUTE)
                    )
                 ORDER BY
                    CASE WHEN e.status = "failed" THEN 0 ELSE 1 END,
                    e.received_at ASC,
                    e.id ASC
                 LIMIT 1000'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function webhookEventById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT e.*, p.code AS provider_code, p.name AS provider_name,
                    p.status AS provider_status, p.adapter_key, p.environment
             FROM billing_webhook_events e
             INNER JOIN billing_payment_providers p ON p.id = e.provider_id
             WHERE e.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,int> */
    public function billingOperationsSummary(): array
    {
        $row = Database::connection()->query(
            'SELECT
                (SELECT COUNT(*) FROM billing_invoices WHERE status = "open") AS open_invoices,
                (
                    SELECT COUNT(*)
                    FROM billing_invoices i
                    WHERE i.status = "open"
                      AND i.due_at IS NOT NULL
                      AND i.due_at < CURRENT_TIMESTAMP
                      AND COALESCE((
                          SELECT SUM(
                              CASE
                                  WHEN t.status <> "succeeded" THEN 0
                                  WHEN t.transaction_type IN ("payment", "credit") THEN t.amount
                                  WHEN t.transaction_type = "refund" THEN -t.amount
                                  ELSE 0
                              END
                          )
                          FROM billing_transactions t
                          WHERE t.invoice_id = i.id
                      ), 0) < i.total - 0.005
                ) AS overdue_invoices,
                (
                    SELECT COUNT(*)
                    FROM billing_invoices i
                    WHERE COALESCE((
                        SELECT SUM(
                            CASE
                                WHEN t.status <> "succeeded" THEN 0
                                WHEN t.transaction_type IN ("payment", "credit") THEN t.amount
                                WHEN t.transaction_type = "refund" THEN -t.amount
                                ELSE 0
                            END
                        )
                        FROM billing_transactions t
                        WHERE t.invoice_id = i.id
                    ), 0) > i.total + 0.005
                ) AS overpaid_invoices,
                (
                    SELECT COUNT(*)
                    FROM billing_invoices i
                    WHERE i.status = "paid"
                      AND COALESCE((
                          SELECT SUM(
                              CASE
                                  WHEN t.status <> "succeeded" THEN 0
                                  WHEN t.transaction_type IN ("payment", "credit") THEN t.amount
                                  WHEN t.transaction_type = "refund" THEN -t.amount
                                  ELSE 0
                              END
                          )
                          FROM billing_transactions t
                          WHERE t.invoice_id = i.id
                      ), 0) < i.total - 0.005
                ) AS paid_mismatches,
                (
                    SELECT COUNT(*)
                    FROM billing_transactions
                    WHERE status = "pending"
                ) AS pending_transactions,
                (
                    SELECT COUNT(*)
                    FROM billing_webhook_events
                    WHERE status = "failed"
                ) AS failed_webhooks,
                (
                    SELECT COUNT(*)
                    FROM billing_webhook_events
                    WHERE status = "received"
                      AND received_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 10 MINUTE)
                ) AS stale_webhooks'
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row)
            ? array_map(static fn (mixed $value): int => (int) $value, $row)
            : [];
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
