<?php

declare(strict_types=1);

use Barama\Core\Database;
use Barama\Core\Session;
use Barama\Repositories\EmailVerificationRepository;
use Barama\Repositories\RoleRepository;
use Barama\Repositories\UserRepository;
use Barama\Security\PasswordPolicy;
use Barama\Services\AccountSettingsService;
use Barama\Services\AuthService;
use Barama\Services\EmailVerificationService;
use Barama\Services\UserAdministrationService;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/app.php';

$pdo = Database::connection();
$users = new UserRepository();
$roles = new RoleRepository();
$password = 'Barama-Release-QA-123!';
$hash = password_hash($password, PASSWORD_DEFAULT);

if (!is_string($hash)) {
    throw new RuntimeException('Unable to create release hardening password hash.');
}

$createUser = static function (
    string $name,
    string $email,
    bool $verified = false,
    bool $administrator = false
) use ($users, $roles, $hash): int {
    $id = $users->create($name, $email, $hash);
    $roles->assignRole($id, 'user');

    if ($verified) {
        $users->markEmailVerified($id);
    }

    if ($administrator) {
        $roles->assignRole($id, 'administrator');
    }

    return $id;
};

$adminId = $createUser(
    'Release QA Administrator',
    'release-admin@example.test',
    true,
    true
);
$customerId = $createUser(
    'Release QA Customer',
    'release-customer@example.test'
);
$passwordUserId = $createUser(
    'Release QA Password User',
    'release-password@example.test',
    true
);

Session::put('auth.user_id', $adminId);

$admin = new UserAdministrationService();
$unverifiedAdminBlocked = false;

try {
    $admin->setAdministrator($customerId, true);
} catch (\DomainException) {
    $unverifiedAdminBlocked = true;
}

if (!$unverifiedAdminBlocked) {
    throw new RuntimeException('Unverified account received administrator access.');
}

$verificationRepository = new EmailVerificationRepository();
$verificationToken = str_repeat('d', 64);
$verificationRepository->replaceForUser(
    $customerId,
    'release-customer@example.test',
    hash('sha256', $verificationToken),
    date('Y-m-d H:i:s', time() + 3600)
);

$verification = new EmailVerificationService();

if ($verification->preview($customerId, $verificationToken) !== 'release-customer@example.test') {
    throw new RuntimeException('Email verification preview did not bind to the account email.');
}

$verification->verify($customerId, $verificationToken);

if (empty($users->findById($customerId)['email_verified_at'])) {
    throw new RuntimeException('Email verification did not mark the customer verified.');
}

$admin->setAdministrator($customerId, true);

if (!$roles->userHasRole($customerId, 'administrator')) {
    throw new RuntimeException('Verified user did not receive administrator role.');
}

$admin->setAdministrator($customerId, false);

if ($roles->userHasRole($customerId, 'administrator')) {
    throw new RuntimeException('Administrator role removal failed.');
}

$selfAdminRemovalBlocked = false;

try {
    $admin->setAdministrator($adminId, false);
} catch (\DomainException) {
    $selfAdminRemovalBlocked = true;
}

if (!$selfAdminRemovalBlocked) {
    throw new RuntimeException('Administrator was able to remove their own role.');
}

$admin->setStatus($customerId, 'suspended');

if ((string) $users->findById($customerId)['status'] !== 'suspended') {
    throw new RuntimeException('Administrator user suspension failed.');
}

$admin->setStatus($customerId, 'active');

$sendUserId = $createUser(
    'Release QA Verify Send',
    'release-verify-send@example.test'
);
$verification->send($sendUserId, true);

$queuedVerification = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM email_outbox
     WHERE to_address = 'release-verify-send@example.test'
       AND subject = 'Verify your Barama Real Estate email'"
)->fetchColumn();

if ($queuedVerification !== 1) {
    throw new RuntimeException('Registration verification email was not queued.');
}

if (
    PasswordPolicy::valid('weak-password')
    || !PasswordPolicy::valid('Barama-Strong-Password-789!')
) {
    throw new RuntimeException('Canonical password policy is inconsistent.');
}

Session::put('auth.user_id', $passwordUserId);
$authBefore = new AuthService();

if (!$authBefore->check()) {
    throw new RuntimeException('CLI authentication setup failed.');
}

$oldVersion = (int) $users->findById($passwordUserId)['auth_version'];
$newPassword = 'Barama-Changed-Password-456!';

(new AccountSettingsService())->changePassword($passwordUserId, [
    'current_password' => $password,
    'password' => $newPassword,
    'password_confirmation' => $newPassword,
]);

$changedUser = $users->findById($passwordUserId);
$newVersion = (int) ($changedUser['auth_version'] ?? 0);

if (
    $newVersion <= $oldVersion
    || !password_verify($newPassword, (string) $changedUser['password_hash'])
    || !(new AuthService())->check()
) {
    throw new RuntimeException('Password change did not rotate auth version while preserving the current session.');
}

Session::put('auth.version', $oldVersion);

if ((new AuthService())->check()) {
    throw new RuntimeException('A stale pre-password-change session remained valid.');
}

$auditCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM security_audit_events
     WHERE event_type IN (
        'auth.email_verified',
        'admin.role_granted',
        'admin.role_revoked',
        'admin.user_status_changed',
        'account.password_changed'
     )"
)->fetchColumn();

if ($auditCount < 5) {
    throw new RuntimeException('Expected security audit events were not recorded.');
}

fwrite(
    STDOUT,
    "[PASS] Release security smoke: verification, canonical password policy, "
    . "session-version invalidation, administrator safeguards, user suspension "
    . "and immutable security audit events verified.\n"
);
