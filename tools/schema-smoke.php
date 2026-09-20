<?php

declare(strict_types=1);

use Barama\Core\Database;
use Barama\Domain\RealEstate\DomainRules;
use PDO;
use RuntimeException;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/app.php';

$pdo = Database::connection();
$failures = [];

$requiredTables = [
    'schema_migrations',
    'users',
    'roles',
    'permissions',
    'user_roles',
    'role_permissions',
    'password_reset_tokens',
    'login_attempts',
    'locations',
    'amenities',
    'companies',
    'company_members',
    'projects',
    'project_amenities',
    'properties',
    'property_amenities',
    'listings',
    'media_assets',
    'media_attachments',
    'inquiries',
    'leads',
    'entity_status_history',
    'inquiry_rate_limits',
    'saved_listings',
    'comparison_items',
    'recent_listing_views',
    'saved_searches',
    'viewing_requests',
    'notification_preferences',
    'notifications',
    'email_outbox',
    'saved_search_matches',
];

$statement = $pdo->query(
    'SELECT TABLE_NAME
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()'
);
$tableNames = array_map(
    static fn (mixed $value): string => (string) $value,
    $statement->fetchAll(PDO::FETCH_COLUMN)
);
$tableLookup = array_fill_keys($tableNames, true);

foreach ($requiredTables as $table) {
    if (!isset($tableLookup[$table])) {
        $failures[] = "Missing table: {$table}";
    }
}

$requiredRoles = ['user', 'developer', 'administrator'];
$roleRows = $pdo->query('SELECT slug FROM roles')->fetchAll(PDO::FETCH_COLUMN);
$roleLookup = array_fill_keys(
    array_map(static fn (mixed $value): string => (string) $value, $roleRows),
    true
);

foreach ($requiredRoles as $role) {
    if (!isset($roleLookup[$role])) {
        $failures[] = "Missing role seed: {$role}";
    }
}

$requiredPermissions = [
    'account.access',
    'developer.access',
    'dashboard.access',
    'location.manage',
    'amenity.manage',
    'company.manage',
    'project.manage',
    'property.manage',
    'listing.manage',
    'media.manage',
    'inquiry.manage',
    'lead.manage',
];

$permissionRows = $pdo
    ->query('SELECT slug FROM permissions')
    ->fetchAll(PDO::FETCH_COLUMN);
$permissionLookup = array_fill_keys(
    array_map(static fn (mixed $value): string => (string) $value, $permissionRows),
    true
);

foreach ($requiredPermissions as $permission) {
    if (!isset($permissionLookup[$permission])) {
        $failures[] = "Missing permission seed: {$permission}";
    }
}

$developerPermissionCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM role_permissions
     INNER JOIN roles ON roles.id = role_permissions.role_id
     INNER JOIN permissions ON permissions.id = role_permissions.permission_id
     WHERE roles.slug = 'developer'
       AND permissions.slug IN (
           'company.manage',
           'project.manage',
           'property.manage',
           'listing.manage',
           'media.manage',
           'inquiry.manage',
           'lead.manage'
       )"
)->fetchColumn();

if ($developerPermissionCount !== 7) {
    $failures[] = 'Developer domain permission assignment is incomplete.';
}

$adminPermissionCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM role_permissions
     INNER JOIN roles ON roles.id = role_permissions.role_id
     INNER JOIN permissions ON permissions.id = role_permissions.permission_id
     WHERE roles.slug = 'administrator'
       AND permissions.slug IN ('location.manage', 'amenity.manage')"
)->fetchColumn();

if ($adminPermissionCount !== 2) {
    $failures[] = 'Administrator catalog permission assignment is incomplete.';
}

if (!DomainRules::canTransition('listing', 'pending', 'published')) {
    $failures[] = 'Expected listing pending -> published transition is unavailable.';
}

if (DomainRules::canTransition('listing', 'published', 'draft')) {
    $failures[] = 'Unsafe listing published -> draft transition is available.';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL] {$failure}\n");
    }

    exit(1);
}

fwrite(
    STDOUT,
    sprintf(
        "[PASS] Schema smoke test: %d tables, roles, permissions and lifecycle rules verified.\n",
        count($requiredTables)
    )
);
