<?php

declare(strict_types=1);

/**
 * Independent aggregate/semantic audit of TWO already compared, offline Pure
 * PHP restore copies. It does not migrate or activate financial, credential,
 * notification or permission data.
 *
 * Run the separate barama_sensitive_restore_compare.php first. This tool
 * deliberately cannot authorize cutover, payment processing or login.
 */
if (PHP_SAPI !== 'cli' || ($argv[1] ?? null) !== '--audit'
    || count($argv) !== 2 || getenv('APP_ENV') !== 'testing'
    || getenv('BARAMA_REHEARSAL_APPROVED') !== 'TWO_DISPOSABLE_CLONES_VERIFIED'
    || getenv('BARAMA_RESTORED_OFFLINE_VERIFIED') !== 'yes'
    || getenv('BARAMA_SENSITIVE_RESTORE_VERIFIED') !== 'TWO_RESTORES_MATCH'
    || getenv('BARAMA_RELEASE_REVIEW_APPROVED') !== 'PRIVATE_READ_ONLY_ONLY') {
    fwrite(STDERR, "FINSEC_AUDIT_DENIED=OFFLINE_TWO_RESTORE_APPROVAL_REQUIRED\n");
    exit(3);
}

/** Only known identifiers and fixed SELECT queries. Never return record data. */
final class BaramaLegacyFinanceSecurityAudit
{
    private const REQUIRED = [
        'companies' => ['id'],
        'users' => ['id', 'status'],
        'roles' => ['id', 'slug'],
        'permissions' => ['id', 'slug'],
        'user_roles' => ['user_id', 'role_id'],
        'role_permissions' => ['role_id', 'permission_id'],
        'company_members' => ['company_id', 'user_id', 'member_role', 'status'],
        'subscription_plans' => ['id'],
        'company_subscriptions' => ['id', 'company_id', 'plan_id', 'status'],
        'billing_invoices' => ['id', 'invoice_number', 'company_id', 'subscription_id', 'status', 'currency', 'subtotal', 'discount_total', 'tax_total', 'total'],
        'billing_invoice_items' => ['id', 'invoice_id', 'quantity', 'unit_amount', 'line_total'],
        'billing_transactions' => ['id', 'invoice_id', 'company_id', 'status', 'currency', 'amount'],
        'user_security_factors' => ['user_id', 'mfa_enabled', 'change_passcode_enabled'],
        'password_reset_tokens' => ['id'],
    ];

    /**
     * These are counts of violations, not a list of affected accounts, invoice
     * IDs, amounts or secrets. Some historical business rules require human
     * approval even if every numerical count is zero.
     */
    private const BLOCKERS = [
        'invoice_without_company' =>
            'SELECT COUNT(*) FROM billing_invoices i LEFT JOIN companies c ON c.id=i.company_id WHERE c.id IS NULL',
        'invoice_subscription_company_mismatch' =>
            'SELECT COUNT(*) FROM billing_invoices i JOIN company_subscriptions s ON s.id=i.subscription_id WHERE i.company_id<>s.company_id',
        'invoice_item_without_invoice' =>
            'SELECT COUNT(*) FROM billing_invoice_items x LEFT JOIN billing_invoices i ON i.id=x.invoice_id WHERE i.id IS NULL',
        'invoice_line_amount_mismatch' =>
            'SELECT COUNT(*) FROM billing_invoice_items WHERE quantity<=0 OR line_total<0 OR ABS(line_total-ROUND(quantity*unit_amount,2))>0.01',
        'invoice_subtotal_line_sum_mismatch' =>
            'SELECT COUNT(*) FROM billing_invoices i JOIN (SELECT invoice_id,SUM(line_total) AS line_sum FROM billing_invoice_items GROUP BY invoice_id) x ON x.invoice_id=i.id WHERE ABS(i.subtotal-x.line_sum)>0.01',
        'invoice_total_arithmetic_mismatch' =>
            'SELECT COUNT(*) FROM billing_invoices WHERE subtotal<0 OR discount_total<0 OR tax_total<0 OR total<0 OR ABS(total-(subtotal-discount_total+tax_total))>0.01',
        'invoice_duplicate_number_casefold' =>
            'SELECT COUNT(*) FROM (SELECT LOWER(TRIM(invoice_number)) AS n FROM billing_invoices GROUP BY LOWER(TRIM(invoice_number)) HAVING COUNT(*)>1) x',
        'transaction_without_company' =>
            'SELECT COUNT(*) FROM billing_transactions t LEFT JOIN companies c ON c.id=t.company_id WHERE c.id IS NULL',
        'transaction_invoice_company_mismatch' =>
            'SELECT COUNT(*) FROM billing_transactions t JOIN billing_invoices i ON i.id=t.invoice_id WHERE t.company_id<>i.company_id',
        'transaction_invoice_currency_mismatch' =>
            'SELECT COUNT(*) FROM billing_transactions t JOIN billing_invoices i ON i.id=t.invoice_id WHERE t.currency<>i.currency',
        'subscription_missing_company_or_plan' =>
            'SELECT COUNT(*) FROM company_subscriptions s LEFT JOIN companies c ON c.id=s.company_id LEFT JOIN subscription_plans p ON p.id=s.plan_id WHERE c.id IS NULL OR p.id IS NULL',
        'role_assignment_missing_user_or_role' =>
            'SELECT COUNT(*) FROM user_roles ur LEFT JOIN users u ON u.id=ur.user_id LEFT JOIN roles r ON r.id=ur.role_id WHERE u.id IS NULL OR r.id IS NULL',
        'role_permission_missing_role_or_permission' =>
            'SELECT COUNT(*) FROM role_permissions rp LEFT JOIN roles r ON r.id=rp.role_id LEFT JOIN permissions p ON p.id=rp.permission_id WHERE r.id IS NULL OR p.id IS NULL',
        'company_membership_missing_user_or_company' =>
            'SELECT COUNT(*) FROM company_members m LEFT JOIN users u ON u.id=m.user_id LEFT JOIN companies c ON c.id=m.company_id WHERE u.id IS NULL OR c.id IS NULL',
        'security_factor_missing_user' =>
            'SELECT COUNT(*) FROM user_security_factors f LEFT JOIN users u ON u.id=f.user_id WHERE u.id IS NULL',
    ];

    /** Nonzero review counts are NOT authorization to import or enable them. */
    private const REVIEW = [
        'historical_privileged_role_assignments' =>
            "SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE r.slug NOT IN ('user')",
        'legacy_roles_requiring_independent_mapping' =>
            'SELECT COUNT(*) FROM roles',
        'legacy_role_permission_links_to_reauthorize' =>
            'SELECT COUNT(*) FROM role_permissions',
        'historical_active_company_owner_or_manager_memberships' =>
            "SELECT COUNT(*) FROM company_members WHERE status='active' AND member_role IN ('owner','manager')",
        'historical_mfa_enabled_to_reenroll' =>
            'SELECT COUNT(*) FROM user_security_factors WHERE mfa_enabled=1',
        'historical_change_passcode_enabled_to_reenroll' =>
            'SELECT COUNT(*) FROM user_security_factors WHERE change_passcode_enabled=1',
        'historical_password_reset_tokens_to_quarantine' =>
            'SELECT COUNT(*) FROM password_reset_tokens',
        'unlinked_transactions_for_ledger_review' =>
            'SELECT COUNT(*) FROM billing_transactions WHERE invoice_id IS NULL',
        'negative_transactions_for_reversal_review' =>
            'SELECT COUNT(*) FROM billing_transactions WHERE amount<0',
        'invoices_without_line_items_for_accounting_review' =>
            'SELECT COUNT(*) FROM billing_invoices i LEFT JOIN billing_invoice_items x ON x.invoice_id=i.id WHERE x.id IS NULL',
        'historical_subscriptions_to_reauthorize' =>
            'SELECT COUNT(*) FROM company_subscriptions',
    ];

    private const OPTIONAL_QUARANTINE = [
        'billing_payment_providers', 'billing_webhook_events',
        'billing_payment_methods', 'user_security_factors',
        'login_attempts', 'security_audit_events', 'email_outbox',
        'email_outbox_events', 'notifications', 'notification_preferences',
        'email_change_tokens', 'email_verification_tokens',
        'company_invitations', 'saved_search_matches',
    ];

    public static function connect(string $role): PDO
    {
        $prefix = 'BARAMA_'.$role.'_';
        $host = (string) getenv($prefix.'HOST');
        $name = (string) getenv($prefix.'DB');
        $user = (string) getenv($prefix.'USER');
        $password = getenv($prefix.'PASSWORD');
        $port = (string) (getenv($prefix.'PORT') ?: '3306');
        if (!in_array($host, ['127.0.0.1', 'localhost'], true)
            || preg_match('/\Abarama_legacy_(?:qa|rehearsal)_[a-z0-9_]{4,32}\z/D', $name) !== 1
            || $user === '' || !is_string($password) || $password === ''
            || preg_match('/\A[0-9]{1,5}\z/D', $port) !== 1
            || (int) $port < 1 || (int) $port > 65535) {
            throw new RuntimeException('INVALID_ISOLATED_SOURCE');
        }

        $pdo = new PDO(
            'mysql:host='.$host.';port='.$port.';dbname='.$name.';charset=utf8mb4',
            $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $name) {
            throw new RuntimeException('SCHEMA_IDENTITY_MISMATCH');
        }
        return $pdo;
    }

    private static function schema(PDO $db): array
    {
        $rows = $db->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,COLUMN_NAME")
            ->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
        }
        return $map;
    }

    private static function count(PDO $db, string $query): int
    {
        return (int) $db->query($query)->fetchColumn();
    }

    private static function aggregate(PDO $db, string $sql): array
    {
        return $db->query($sql)->fetchAll(PDO::FETCH_NUM);
    }

    public static function inspect(PDO $db): array
    {
        $schema = self::schema($db);
        $missing = [];
        foreach (self::REQUIRED as $table => $columns) {
            foreach ($columns as $column) {
                if (!in_array($column, $schema[$table] ?? [], true)) {
                    $missing[] = $table.'.'.$column;
                }
            }
        }
        if ($missing !== []) {
            return ['schema_gate' => 'BLOCKED', 'missing_required_columns' => $missing];
        }

        $checks = $review = $quarantine = $counts = [];
        foreach (self::BLOCKERS as $key => $query) {
            $checks[$key] = self::count($db, $query);
        }
        foreach (self::REVIEW as $key => $query) {
            $review[$key] = self::count($db, $query);
        }
        foreach (self::REQUIRED as $table => $unused) {
            $counts[$table] = self::count($db, 'SELECT COUNT(*) FROM '.$table);
        }
        foreach (self::OPTIONAL_QUARANTINE as $table) {
            $quarantine[$table] = isset($schema[$table])
                ? self::count($db, 'SELECT COUNT(*) FROM '.$table)
                : 'MISSING_REQUIRES_INDEPENDENT_REVIEW';
        }
        // Per-currency/status sums are compared in process, never disclosed.
        $totals = [
            'invoices' => self::aggregate($db,
                'SELECT currency,status,COUNT(*),SUM(subtotal),SUM(discount_total),SUM(tax_total),SUM(total)
                 FROM billing_invoices GROUP BY currency,status ORDER BY currency,status'),
            'transactions' => self::aggregate($db,
                'SELECT currency,status,COUNT(*),SUM(amount)
                 FROM billing_transactions GROUP BY currency,status ORDER BY currency,status'),
        ];
        return [
            'schema_gate' => 'PASS', 'blocker_counts' => $checks,
            'review_queue_counts' => $review,
            'quarantine_table_rows' => $quarantine,
            'covered_table_rows' => $counts,
            'private_money_totals' => $totals,
        ];
    }

    public static function compare(array $a, array $b): array
    {
        if ($a['schema_gate'] !== 'PASS' || $b['schema_gate'] !== 'PASS') {
            return [
                'schema_gate' => 'BLOCKED',
                'missing_required_columns' => array_values(array_unique(
                    array_merge($a['missing_required_columns'] ?? [], $b['missing_required_columns'] ?? [])
                )),
                'semantic_integrity' => 'BLOCKED',
                'release' => 'BLOCKED',
            ];
        }
        $totalsEqual = $a['private_money_totals'] === $b['private_money_totals'];
        $otherMatch = $a['blocker_counts'] === $b['blocker_counts']
            && $a['review_queue_counts'] === $b['review_queue_counts']
            && $a['quarantine_table_rows'] === $b['quarantine_table_rows']
            && $a['covered_table_rows'] === $b['covered_table_rows'];
        $bad = array_keys(array_filter(
            $a['blocker_counts'], static fn (int $count): bool => $count !== 0
        ));
        $pass = $totalsEqual && $otherMatch && $bad === [];
        return [
            'audit' => 'barama-finance-security-semantic-readonly',
            'source' => 'two_distinct_offline_legacy_restores',
            'mode' => 'READ_ONLY',
            'schema_gate' => 'PASS',
            'blocking_anomaly_counts' => $a['blocker_counts'],
            'blocking_check_names' => $bad,
            'historical_review_queue_counts' => $a['review_queue_counts'],
            'quarantined_source_table_counts' => $a['quarantine_table_rows'],
            'covered_table_counts' => $a['covered_table_rows'],
            'two_restore_aggregate_consistency' => ($totalsEqual && $otherMatch) ? 'MATCH' : 'BLOCKED',
            'semantic_integrity' => $pass ? 'CHECKED_NO_KNOWN_ANOMALIES' : 'BLOCKED',
            'sensitive_values_or_amounts_printed' => false,
            'database_or_account_mutated' => false,
            'legacy_credentials_or_roles_activated' => false,
            'payments_or_notifications_activated' => false,
            'independent_human_approval' => 'NOT_PERFORMED',
            'release' => 'BLOCKED',
        ];
    }
}

try {
    if (getenv('BARAMA_SOURCE_DB') === getenv('BARAMA_RESTORED_DB')) {
        throw new RuntimeException('DISTINCT_RESTORES_REQUIRED');
    }
    $source = BaramaLegacyFinanceSecurityAudit::connect('SOURCE');
    $restore = BaramaLegacyFinanceSecurityAudit::connect('RESTORED');
    $result = BaramaLegacyFinanceSecurityAudit::compare(
        BaramaLegacyFinanceSecurityAudit::inspect($source),
        BaramaLegacyFinanceSecurityAudit::inspect($restore)
    );
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    exit(($result['semantic_integrity'] ?? '') === 'CHECKED_NO_KNOWN_ANOMALIES' ? 0 : 2);
} catch (Throwable $e) {
    // Suppress data, query, connection, schema, path and credential details.
    fwrite(STDERR, "FINSEC_AUDIT_FAILED=PRIVATE_SCHEMA_OR_READ_ONLY_GATE\n");
    exit(4);
}
