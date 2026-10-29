<?php

declare(strict_types=1);

namespace Barama\Http\Middleware;

use Barama\Core\MiddlewareInterface;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\SecurityFactorService;

final class ChangeConfirmationMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $user = auth_user();
        if ($user === null) {
            return $next($request);
        }

        if ($this->isPasscodeManagementAction($request)) {
            return $next($request);
        }

        $service = new SecurityFactorService();
        $userId = (int) $user['id'];

        if ($this->isAuthorizedMfaSetupAction($request, $service, $userId)) {
            return $next($request);
        }
        if (!$service->passcodeEnabled($userId)) {
            return $next($request);
        }

        $grant = trim((string) $request->input('change_confirmation', ''));
        if ($service->consumeChangeGrant($userId, $grant)) {
            return $next($request);
        }

        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_contains($accept, 'application/json')) {
            return Response::json([
                'ok' => false,
                'error' => 'change_confirmation_required',
                'message' => 'Enter your 6-digit change passcode before this change can be applied.',
            ], 428);
        }

        // The response must work outside /dashboard/ too. Reuse the shipped
        // Change Confirmation stylesheet without inline styles or handlers.
        // The dashboard output-buffer renderer may replace this markup with
        // its full branded page; both paths preserve HTTP 428.
        return Response::html(
            <<<'HTML'
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Change confirmation required | Barama</title>
<link rel="stylesheet" href="/assets/css/change-confirmation-page.css">
</head>
<body>
<main class="bcc-page">
  <section class="bcc-shell" aria-labelledby="confirmation-title">
    <article class="bcc-card">
      <p class="bcc-eyebrow">Security check</p>
      <h1 id="confirmation-title">Change confirmation required</h1>
      <p class="bcc-lead">Return to the previous page and confirm this change with your 6-digit passcode.</p>
      <p class="bcc-footnote">Your change has not been applied.</p>
    </article>
  </section>
</main>
</body>
</html>
HTML,
            428
        );
    }

    private function isAuthorizedMfaSetupAction(
        Request $request,
        SecurityFactorService $service,
        int $userId
    ): bool {
        if (!str_ends_with($request->path(), '/settings/security')) {
            return false;
        }

        $action = strtolower(trim((string) $request->input('security_action', '')));
        return in_array($action, ['mfa_enable', 'mfa_cancel'], true)
            && $service->mfaSetupPending($userId);
    }

    private function isPasscodeManagementAction(Request $request): bool
    {
        if (!str_ends_with($request->path(), '/settings/security')) {
            return false;
        }

        return in_array(
            strtolower(trim((string) $request->input('security_action', ''))),
            ['passcode_set', 'passcode_disable'],
            true
        );
    }
}
