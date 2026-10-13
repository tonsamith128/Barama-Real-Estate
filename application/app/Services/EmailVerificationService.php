<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\EmailVerificationRepository;
use Barama\Repositories\UserRepository;
use DateInterval;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class EmailVerificationService
{
    public function send(int $userId, bool $force = false): bool
    {
        $users = new UserRepository();
        $user = $users->findById($userId);

        if ($user === null || (string) $user['status'] !== 'active') {
            throw new DomainException('The account is unavailable.');
        }

        if (!empty($user['email_verified_at'])) {
            return false;
        }

        $repository = new EmailVerificationRepository();
        $resendSeconds = max(60, (int) config('auth.email_verification_resend_seconds', 120));

        if (!$force && $repository->recentlyIssued($userId, $resendSeconds)) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        $minutes = max(30, (int) config('auth.email_verification_minutes', 1440));
        $expiresAt = (new DateTimeImmutable())
            ->add(new DateInterval("PT{$minutes}M"))
            ->format('Y-m-d H:i:s');

        $repository->replaceForUser(
            $userId,
            (string) $user['email'],
            hash('sha256', $token),
            $expiresAt
        );

        $verificationUrl = url('/verify-email/' . rawurlencode($token));

        (new NotificationService())->notifyAddress((string) $user['email'], [
            'template_key' => 'auth_email_verification',
            'user_id' => $userId,
            'variables' => [
                'recipient_name' => (string) ($user['name'] ?? ''),
                'action_url' => $verificationUrl,
                'expires_minutes' => (string) $minutes,
            ],
            'subject' => 'Verify your Barama Real Estate email',
            'html' => '<p><strong>Verify your email address</strong></p>'
                . '<p>Confirm this address to secure your Barama account and unlock company access.</p>'
                . '<p><a href="' . e($verificationUrl) . '">Verify email address</a></p>'
                . '<p>This link expires in ' . $minutes . ' minutes.</p>',
            'text' => "Verify your Barama Real Estate email:\n\n"
                . $verificationUrl
                . "\n\nThis link expires in {$minutes} minutes.",
            'dedupe_key' => 'verify-email:' . $userId . ':' . hash('sha256', $token),
        ]);

        (new SecurityAuditService())->record(
            'auth.email_verification_sent',
            $userId,
            $userId
        );

        return true;
    }

    public function preview(int $userId, string $token): ?string
    {
        if (!$this->validTokenFormat($token)) {
            return null;
        }

        $record = (new EmailVerificationRepository())->findValid(
            $userId,
            hash('sha256', $token)
        );

        return $record !== null ? (string) $record['email'] : null;
    }

    public function verify(int $userId, string $token): void
    {
        if (!$this->validTokenFormat($token)) {
            throw new InvalidArgumentException('This verification link is invalid or expired.');
        }

        $repository = new EmailVerificationRepository();
        $record = $repository->findValid($userId, hash('sha256', $token));

        if ($record === null) {
            throw new InvalidArgumentException('This verification link is invalid or expired.');
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            (new UserRepository())->markEmailVerified($userId);
            $repository->markUsed((int) $record['id']);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        (new SecurityAuditService())->record(
            'auth.email_verified',
            $userId,
            $userId
        );
    }

    private function validTokenFormat(string $token): bool
    {
        return strlen($token) === 64 && ctype_xdigit($token);
    }
}
