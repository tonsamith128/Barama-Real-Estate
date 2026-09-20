<?php

declare(strict_types=1);

use Barama\Core\Database;
use Barama\Core\Session;
use Barama\Repositories\AccountSettingsRepository;
use Barama\Repositories\CompanyMembershipRepository;
use Barama\Repositories\EmailVerificationRepository;
use Barama\Repositories\RoleRepository;
use Barama\Repositories\UserRepository;
use Barama\Services\AccountSettingsService;
use Barama\Services\CompanyOnboardingService;
use Barama\Services\CustomerEngagementService;
use Barama\Services\DomainWorkflowService;
use Barama\Services\EmailVerificationService;
use Barama\Services\MembershipService;
use DomainException;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/app.php';

$pdo = Database::connection();
$users = new UserRepository();
$roles = new RoleRepository();
$password = 'Barama-Onboarding-QA-123!';
$hash = password_hash($password, PASSWORD_DEFAULT);

if (!is_string($hash)) {
    throw new RuntimeException('Unable to create onboarding QA password hash.');
}

$makeUser = static function (
    string $name,
    string $email,
    string $role = 'user'
) use ($users, $roles, $hash): int {
    $id = $users->create($name, $email, $hash);
    $roles->assignRole($id, 'user');

    if ($role !== 'user') {
        $roles->assignRole($id, $role);
    }

    return $id;
};

$adminId = $makeUser('Onboarding QA Admin', 'onboarding-admin@example.test', 'administrator');
$ownerId = $makeUser('Onboarding QA Owner', 'onboarding-owner@example.test');
$memberId = $makeUser('Onboarding QA Member', 'onboarding-member@example.test');

$verificationRepository = new EmailVerificationRepository();
$verificationService = new EmailVerificationService();

foreach ([
    [$ownerId, 'onboarding-owner@example.test', str_repeat('b', 64)],
    [$memberId, 'onboarding-member@example.test', str_repeat('c', 64)],
] as [$verificationUserId, $verificationEmail, $verificationToken]) {
    $verificationRepository->replaceForUser(
        $verificationUserId,
        $verificationEmail,
        hash('sha256', $verificationToken),
        date('Y-m-d H:i:s', time() + 3600)
    );
    $verificationService->verify($verificationUserId, $verificationToken);
}

$locationId = (int) $pdo->query(
    'SELECT id FROM locations WHERE status = "active" ORDER BY id ASC LIMIT 1'
)->fetchColumn();

Session::put('auth.user_id', $ownerId);

$settings = new AccountSettingsService();
$settings->updateProfile($ownerId, [
    'name' => 'Updated Onboarding Owner',
    'phone' => '+85510000000',
    'timezone' => 'Asia/Phnom_Penh',
    'preferred_language' => 'km',
]);

$profile = (new AccountSettingsRepository())->profile($ownerId);

if (
    ($profile['name'] ?? '') !== 'Updated Onboarding Owner'
    || ($profile['timezone'] ?? '') !== 'Asia/Phnom_Penh'
    || ($profile['preferred_language'] ?? '') !== 'km'
) {
    throw new RuntimeException('Account profile settings were not persisted.');
}

$companyId = (new CompanyOnboardingService())->submit([
    'name' => 'Onboarding QA Property Group',
    'company_type' => 'agency',
    'location_id' => $locationId > 0 ? $locationId : null,
    'email' => 'onboarding-company@example.test',
    'phone' => '+85523000000',
    'description' => 'QA onboarding company.',
]);

$statusStatement = $pdo->prepare('SELECT status FROM companies WHERE id = :id');
$statusStatement->execute(['id' => $companyId]);

if ((string) $statusStatement->fetchColumn() !== 'pending') {
    throw new RuntimeException('Customer onboarding did not submit the company for review.');
}

if ($roles->userHasRole($ownerId, 'developer')) {
    throw new RuntimeException('Developer role was granted before company approval.');
}

$membership = (new CompanyMembershipRepository())->findActiveMembership($companyId, $ownerId);

if ($membership === null || (string) $membership['member_role'] !== 'owner') {
    throw new RuntimeException('Onboarding did not establish company ownership.');
}

Session::put('auth.user_id', $adminId);
(new DomainWorkflowService())->transition('company', $companyId, 'approved', 'Onboarding QA approval');

if (!$roles->userHasRole($ownerId, 'developer')) {
    throw new RuntimeException('Company approval did not activate the owner Developer role.');
}

Session::put('auth.user_id', $ownerId);
$invitationId = (new MembershipService())->inviteByEmail(
    $companyId,
    'onboarding-member@example.test',
    'manager'
);

if ($invitationId < 1 || $roles->userHasRole($memberId, 'developer')) {
    throw new RuntimeException('Team invitation granted access before acceptance.');
}

Session::put('auth.user_id', $memberId);
$acceptedCompanyId = (new MembershipService())->acceptInvitation($invitationId);

if ($acceptedCompanyId !== $companyId || !$roles->userHasRole($memberId, 'developer')) {
    throw new RuntimeException('Invitation acceptance did not activate Developer access.');
}

$memberMembership = (new CompanyMembershipRepository())->findActiveMembership(
    $companyId,
    $memberId
);

if ($memberMembership === null || (string) $memberMembership['member_role'] !== 'manager') {
    throw new RuntimeException('Accepted invitation did not create the expected membership.');
}

$settings->updatePrivacy($memberId, ['remember_recent_views' => '0']);
$listingId = (int) $pdo->query(
    'SELECT id FROM listings WHERE status = "published" ORDER BY id ASC LIMIT 1'
)->fetchColumn();

if ($listingId > 0) {
    (new CustomerEngagementService())->recordView($memberId, $listingId);

    $viewCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM recent_listing_views WHERE user_id = ' . $memberId
    )->fetchColumn();

    if ($viewCount !== 0) {
        throw new RuntimeException('Privacy preference did not suppress recent-view tracking.');
    }
}

$newPassword = 'Barama-Onboarding-New-456!';
$settings->changePassword($memberId, [
    'current_password' => $password,
    'password' => $newPassword,
    'password_confirmation' => $newPassword,
]);

$memberUser = $users->findById($memberId);

if (
    $memberUser === null
    || !password_verify($newPassword, (string) $memberUser['password_hash'])
) {
    throw new RuntimeException('Secure account password change failed.');
}

$settings->requestEmailChange($memberId, [
    'current_password' => $newPassword,
    'new_email' => 'onboarding-member-requested@example.test',
]);

$emailRequestCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM email_change_tokens
     WHERE user_id = {$memberId}
       AND new_email = 'onboarding-member-requested@example.test'
       AND used_at IS NULL"
)->fetchColumn();
$emailQueueCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM email_outbox
     WHERE to_address = 'onboarding-member-requested@example.test'"
)->fetchColumn();

if ($emailRequestCount !== 1 || $emailQueueCount !== 1) {
    throw new RuntimeException('Email change request did not create a token and queued confirmation.');
}

$knownToken = str_repeat('a', 64);
$accountRepo = new AccountSettingsRepository();
$accountRepo->createEmailChangeToken(
    $memberId,
    'onboarding-member-new@example.test',
    hash('sha256', $knownToken),
    date('Y-m-d H:i:s', time() + 3600)
);
$settings->confirmEmailChange($memberId, $knownToken);

$updatedMember = $users->findById($memberId);

if (
    $updatedMember === null
    || (string) $updatedMember['email'] !== 'onboarding-member-new@example.test'
    || empty($updatedMember['email_verified_at'])
) {
    throw new RuntimeException('Verified email change did not update the account.');
}

Session::put('auth.user_id', $ownerId);
$ownerDeactivationBlocked = false;

try {
    $settings->deactivateAccount($ownerId, ['current_password' => $password]);
} catch (DomainException) {
    $ownerDeactivationBlocked = true;
}

if (!$ownerDeactivationBlocked) {
    throw new RuntimeException('Company owner account deactivation was not blocked.');
}

fwrite(
    STDOUT,
    "[PASS] Account/onboarding smoke: profile, privacy, password, verified email, "
    . "company request, approval-driven Developer activation, invitation acceptance "
    . "and owner deactivation protection verified.\n"
);
