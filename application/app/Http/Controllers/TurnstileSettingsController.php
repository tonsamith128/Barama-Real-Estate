<?php
declare(strict_types=1);
namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Security\TurnstileGuard;
use Throwable;

/**
 * Turnstile configuration actions for the existing Security table modal.
 * GET legacy URL redirects to Security; NO standalone settings page is rendered.
 * AuthMiddleware, dashboard permission and CsrfMiddleware are in the existing route.
 */
final class TurnstileSettingsController
{
    private const SECURITY = '/dashboard/settings/security';

    public function show(Request $request): Response
    {
        return Response::redirect(url(self::SECURITY . '?turnstile=1'));
    }

    public function change(Request $request): Response
    {
        $operation = $request->input('operation', '');
        try {
            $settings = TurnstileGuard::load();
            if ($operation === 'save') {
                $site = trim((string) $request->input('site_key', ''));
                $secret = trim((string) $request->input('secret_key', ''));
                if ($secret === '') {
                    $secret = $settings['secret'];
                }
                $forms = $this->forms($request);
                $appearance = $request->input('appearance', 'always');
                if (!is_string($appearance) || !in_array($appearance, TurnstileGuard::APPEARANCES, true)) {
                    return $this->feedback('Select a valid Cloudflare appearance mode.', true);
                }
                TurnstileGuard::save([
                    'site' => $site, 'secret' => $secret, 'forms' => $forms,
                    'enabled' => false, 'verified' => 0, 'appearance' => $appearance,
                ]);
                return $this->feedback('Keys saved securely. Protection stays disabled until real Cloudflare verification.');
            }
            if ($operation === 'verify') {
                $now = time();
                $attempts = $_SESSION['barama_turnstile_setup_attempts'] ?? [];
                $attempts = is_array($attempts) ? $attempts : [];
                $attempts = array_values(array_filter($attempts, static fn (mixed $t): bool =>
                    is_int($t) && $t > $now - 60 && $t <= $now));
                if (count($attempts) >= 8) {
                    return $this->feedback('Too many verification attempts. Wait one minute.', true);
                }
                $attempts[] = $now;
                $_SESSION['barama_turnstile_setup_attempts'] = $attempts;
                $token = $request->input('cf-turnstile-response', '');
                if (!is_string($token) || !TurnstileGuard::verify($token, 'barama_setup', $request->ip())) {
                    return $this->feedback('Cloudflare verification failed. Protection remains unchanged.', true);
                }
                $settings['verified'] = time();
                $settings['enabled'] = false;
                TurnstileGuard::save($settings);
                return $this->feedback('Cloudflare verified your real credentials. You can now enable selected forms.');
            }
            if ($operation === 'enable') {
                if ($settings['verified'] < time() - 3600 || $settings['verified'] > time() + 60) {
                    return $this->feedback('Re-verify with Cloudflare before enabling protection.', true);
                }
                if ($settings['forms'] === []) {
                    return $this->feedback('Select at least one protected form first.', true);
                }
                $settings['enabled'] = true;
                TurnstileGuard::save($settings);
                return $this->feedback('Turnstile has been enabled for the selected forms.', false, true);
            }
            if ($operation === 'disable') {
                if ($settings['secret'] === '') {
                    return $this->feedback('Turnstile is already disabled.');
                }
                $settings['enabled'] = false;
                TurnstileGuard::save($settings);
                return $this->feedback('Turnstile has been disabled on public forms.', false, true);
            }
            return $this->feedback('Invalid security action.', true);
        } catch (Throwable) {
            return $this->feedback('Turnstile settings unavailable. Check installation encryption key and private storage.', true);
        }
    }

    private function forms(Request $request): array
    {
        $input = $request->input('forms', []);
        if (!is_array($input) || count($input) > count(TurnstileGuard::ACTIONS)) {
            throw new \InvalidArgumentException('Invalid protected form choice.');
        }
        $forms = [];
        foreach ($input as $form) {
            if (!is_string($form) || !in_array($form, TurnstileGuard::ACTIONS, true)) {
                throw new \InvalidArgumentException('Unknown protected form.');
            }
            $forms[] = $form;
        }
        return array_values(array_unique($forms));
    }

    private function feedback(string $message, bool $error = false, bool $close = false): Response
    {
        if ($close && !$error) {
            // A successful enable/disable action should return to the real table.
            // Show the confirmation via the existing shared Settings flash banner.
            return Response::redirect(url(self::SECURITY . '?saved=1&message=' . rawurlencode($message)), 303);
        }
        // No secrets or raw Cloudflare errors are ever stored in session flash.
        $_SESSION['barama_turnstile_feedback'] = [
            'message' => $message,
            'error' => $error,
        ];
        return Response::redirect(url(self::SECURITY . '?turnstile=1'), 303);
    }
}
