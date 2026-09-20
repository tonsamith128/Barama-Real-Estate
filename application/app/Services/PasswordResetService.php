<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\PasswordResetRepository;
use Barama\Repositories\UserRepository;
use DateInterval;
use DateTimeImmutable;
use Throwable;

final class PasswordResetService
{
    private UserRepository $users;
    private PasswordResetRepository $tokens;
    private MailService $mailer;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->tokens = new PasswordResetRepository();
        $this->mailer = new MailService();
    }

    public function request(string $email): void
    {
        $email = strtolower(trim($email));
        $user = $this->users->findByEmail($email);

        if ($user === null || (string) $user['status'] !== 'active') {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $minutes = max(10, (int) config('auth.password_reset_minutes', 60));
        $expiresAt = (new DateTimeImmutable())
            ->add(new DateInterval("PT{$minutes}M"))
            ->format('Y-m-d H:i:s');

        $this->tokens->replaceForUser((int) $user['id'], $tokenHash, $expiresAt);

        $resetUrl = url('/reset-password/' . rawurlencode($token))
            . '?email=' . rawurlencode($email);

        $subject = 'Reset your Barama Real Estate password';
        $safeUrl = e($resetUrl);

        $html = '<p>We received a request to reset your Barama Real Estate password.</p>'
            . '<p><a href="' . $safeUrl . '">Reset password</a></p>'
            . '<p>This link expires in ' . $minutes . ' minutes.</p>'
            . '<p>If you did not request this, you can ignore this email.</p>';

        $text = "We received a request to reset your Barama Real Estate password.\n\n"
            . "Reset password: {$resetUrl}\n\n"
            . "This link expires in {$minutes} minutes.\n"
            . "If you did not request this, you can ignore this email.";

        if (!$this->mailer->send($email, $subject, $html, $text)) {
            $this->tokens->deleteByHash($tokenHash);
        }
    }

    public function isValid(string $token, string $email): bool
    {
        if (!$this->validTokenFormat($token) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return $this->tokens->findValid(
            hash('sha256', $token),
            $email
        ) !== null;
    }

    public function reset(string $token, string $email, string $password): bool
    {
        if (!$this->validTokenFormat($token)) {
            return false;
        }

        $tokenHash = hash('sha256', $token);
        $record = $this->tokens->findValid($tokenHash, $email);

        if ($record === null) {
            return false;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if (!is_string($passwordHash)) {
            return false;
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $this->users->updatePassword((int) $record['user_id'], $passwordHash);
            $this->tokens->markUsed((int) $record['id']);

            $pdo->commit();

            return true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function validTokenFormat(string $token): bool
    {
        return strlen($token) === 64 && ctype_xdigit($token);
    }
}
