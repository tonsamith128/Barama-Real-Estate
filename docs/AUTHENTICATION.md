# Laravel authentication milestone

This milestone implements a NEW Laravel authentication subsystem; it does **not** migrate legacy Barama users, roles, company membership, or MFA.

## Source map

| Feature | HTTP controller | Laravel facility |
|---|---|---|
| Login | app/Http/Controllers/Auth/Login/LoginController.php | Auth::attempt, RateLimiter, session regeneration |
| Register | app/Http/Controllers/Auth/Register/RegisterController.php | FormRequest, hashed cast, Registered event |
| Logout | app/Http/Controllers/Auth/Logout/LogoutController.php | Auth::logout, session invalidation |
| Forgot password | app/Http/Controllers/Auth/ForgotPassword/ForgotPasswordController.php | Password broker |
| Reset password | app/Http/Controllers/Auth/ResetPassword/ResetPasswordController.php | Password::reset, PasswordReset event |
| Confirm email | app/Http/Controllers/Auth/ConfirmEmail/EmailVerificationController.php | signed URLs, EmailVerificationRequest |
| Account foundation | app/Http/Controllers/Account/Dashboard/DashboardController.php | auth and verified middleware |

## Active endpoints

- GET/POST /login
- GET/POST /register
- GET/POST /forgot-password
- GET /reset-password/{token} and POST /reset-password
- GET /verify-email and GET /verify-email/{id}/{hash}
- POST /email/verification-notification
- POST /logout
- GET /account (verified session required)

## Current data

Isolated development migration adds only `users` and `password_reset_tokens`; it is NOT a mapping for the v1 Barama database. Run only on an empty/test v2 database until schema/data compatibility is documented.

## Security

- Strong password policy at registration/reset: at least 12 characters, mixed case, number and symbol.
- Requests use Laravel CSRF and session web middleware.
- Login failures are rate-limited by email/IP; register/reset and verification actions have route throttles.
- No email account disclosure in forgot-password responses.
- Passwords are stored through Eloquent hashed casts.
- Authenticated Account routes require verified email.
- Signed email verification link uses Laravel's native URL validation.
- The existing v1 MFA, auth-version invalidation, user state and role permissions are **not migrated**; this is not a production-ready auth replacement.

## Development tests (authored, not yet executed)

- tests/Feature/Auth/AuthenticationFlowTest.php
- tests/Feature/Auth/EmailVerificationTest.php
- tests/Feature/Auth/PasswordResetTest.php

Run: `composer install`, `composer test`, `npm run build`. CI must pass before a branch merge or deployment.
