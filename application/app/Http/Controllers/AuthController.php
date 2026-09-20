<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\Session;
use Barama\Core\View;
use Barama\Services\AuthService;
use Barama\Services\PasswordResetService;
use RuntimeException;

final class AuthController
{
    public function showLogin(Request $request): Response
    {
        return $this->authView('auth/login', 'Sign in', [
            'email' => '',
            'errors' => [],
            'success' => $request->input('reset') === '1'
                ? 'Your password has been updated. You can sign in now.'
                : null,
        ]);
    }

    public function login(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $errors[] = 'Enter a valid email address and password.';
        }

        if ($errors !== []) {
            return $this->authView('auth/login', 'Sign in', [
                'email' => $email,
                'errors' => $errors,
                'success' => null,
            ], 422);
        }

        $auth = new AuthService();
        $result = $auth->attempt($email, $password, $request->ip());

        if ($result === 'locked') {
            return $this->authView('auth/login', 'Sign in', [
                'email' => $email,
                'errors' => ['Too many login attempts. Please try again later.'],
                'success' => null,
            ], 429);
        }

        if ($result !== 'success') {
            return $this->authView('auth/login', 'Sign in', [
                'email' => $email,
                'errors' => ['The email address or password is incorrect.'],
                'success' => null,
            ], 422);
        }

        $intended = Session::pull('auth.intended');
        $destination = is_string($intended)
            && str_starts_with($intended, '/')
            && !str_starts_with($intended, '//')
                ? $intended
                : $auth->homePath();

        return Response::redirect(url($destination));
    }

    public function showRegister(Request $request): Response
    {
        return $this->authView('auth/register', 'Create account', [
            'name' => '',
            'email' => '',
            'errors' => [],
        ]);
    }

    public function register(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $confirmation = (string) $request->input('password_confirmation', '');
        $errors = [];

        if (strlen($name) < 2 || strlen($name) > 120) {
            $errors[] = 'Your name must be between 2 and 120 characters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
            $errors[] = 'Enter a valid email address.';
        }

        $minimum = max(12, (int) config('auth.password_min_length', 12));

        if (strlen($password) < $minimum) {
            $errors[] = "Use a password with at least {$minimum} characters.";
        }

        if ($password !== $confirmation) {
            $errors[] = 'The password confirmation does not match.';
        }

        if ($errors !== []) {
            return $this->authView('auth/register', 'Create account', [
                'name' => $name,
                'email' => $email,
                'errors' => $errors,
            ], 422);
        }

        try {
            $auth = new AuthService();
            $auth->register($name, $email, $password);

            return Response::redirect(url('/account'));
        } catch (RuntimeException $exception) {
            return $this->authView('auth/register', 'Create account', [
                'name' => $name,
                'email' => $email,
                'errors' => [$exception->getMessage()],
            ], 422);
        }
    }

    public function logout(Request $request): Response
    {
        (new AuthService())->logout();

        return Response::redirect(url('/login'));
    }

    public function showForgotPassword(Request $request): Response
    {
        return $this->authView('auth/forgot-password', 'Forgot password', [
            'email' => '',
            'errors' => [],
            'success' => null,
        ]);
    }

    public function sendResetLink(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->authView('auth/forgot-password', 'Forgot password', [
                'email' => $email,
                'errors' => ['Enter a valid email address.'],
                'success' => null,
            ], 422);
        }

        (new PasswordResetService())->request($email);

        return $this->authView('auth/forgot-password', 'Forgot password', [
            'email' => '',
            'errors' => [],
            'success' => 'If an active account matches that email, a password reset link has been sent.',
        ]);
    }

    public function showResetPassword(Request $request, string $token): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $valid = (new PasswordResetService())->isValid($token, $email);

        return $this->authView('auth/reset-password', 'Reset password', [
            'token' => $token,
            'email' => $email,
            'valid' => $valid,
            'errors' => $valid ? [] : ['This password reset link is invalid or has expired.'],
        ], $valid ? 200 : 422);
    }

    public function resetPassword(Request $request, string $token): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $confirmation = (string) $request->input('password_confirmation', '');
        $minimum = max(12, (int) config('auth.password_min_length', 12));
        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'The password reset request is invalid.';
        }

        if (strlen($password) < $minimum) {
            $errors[] = "Use a password with at least {$minimum} characters.";
        }

        if ($password !== $confirmation) {
            $errors[] = 'The password confirmation does not match.';
        }

        if ($errors !== []) {
            return $this->authView('auth/reset-password', 'Reset password', [
                'token' => $token,
                'email' => $email,
                'valid' => true,
                'errors' => $errors,
            ], 422);
        }

        $reset = (new PasswordResetService())->reset($token, $email, $password);

        if (!$reset) {
            return $this->authView('auth/reset-password', 'Reset password', [
                'token' => $token,
                'email' => $email,
                'valid' => false,
                'errors' => ['This password reset link is invalid or has expired.'],
            ], 422);
        }

        return Response::redirect(url('/login?reset=1'));
    }

    /** @param array<string, mixed> $data */
    private function authView(
        string $view,
        string $pageTitle,
        array $data,
        int $status = 200
    ): Response {
        return Response::html(
            View::render($view, array_merge($data, [
                'pageTitle' => $pageTitle,
                'activeArea' => 'auth',
            ])),
            $status
        );
    }
}
