<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\TurnstilePlatformTestService;

/** A safe, admin-session-only, in-platform test page. Not customer protection. */
final class TurnstilePlatformTestController
{
    private const PATH = '/dashboard/settings/security/turnstile-test';

    public function show(Request $request): Response
    {
        $scenario = $request->input('scenario', 'pass');
        return $this->page(is_string($scenario) && in_array($scenario, ['pass', 'fail', 'replay'], true)
            ? $scenario : 'pass', null, 200);
    }

    public function verify(Request $request): Response
    {
        $scenario = $request->input('scenario', '');
        $token = $request->input('cf-turnstile-response', '');
        if (!is_string($scenario) || !in_array($scenario, ['pass', 'fail', 'replay'], true)
            || !is_string($token)) {
            return $this->page('pass', ['transport' => false, 'success' => false,
                'errors' => ['invalid-request']], 422);
        }

        // Admin-session-only rate limit. No production data or extra DB tables.
        $now = time();
        $events = $_SESSION['barama_turnstile_qa_events'] ?? [];
        $events = is_array($events) ? $events : [];
        $events = array_values(array_filter($events,
            static fn (mixed $time): bool => is_int($time) && $time > $now - 60 && $time <= $now
        ));
        if (count($events) >= 12) {
            $_SESSION['barama_turnstile_qa_events'] = $events;
            return $this->page($scenario, ['transport' => false, 'success' => false,
                'errors' => ['rate-limited']], 429);
        }
        $events[] = $now;
        $_SESSION['barama_turnstile_qa_events'] = $events;
        $result = TurnstilePlatformTestService::verify($token, $scenario);
        return $this->page($scenario, $result, $result['success'] ? 200 : 422);
    }

    /** @param array{transport:bool,success:bool,errors:array<int,string>}|null $result */
    private function page(string $scenario, ?array $result, int $status): Response
    {
        $escape = static fn (string $value): string => htmlspecialchars(
            $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'
        );
        $siteKey = $escape(TurnstilePlatformTestService::SITE_KEY);
        $action = $escape(url(self::PATH));
        $csrf = csrf_field();
        $tabs = '';
        foreach (['pass' => 'Expected pass', 'fail' => 'Expected reject',
                  'replay' => 'Expected duplicate'] as $mode => $label) {
            $on = $scenario === $mode ? ' active' : '';
            $tabs .= '<a class="tab' . $on . '" href="' . $action . '?scenario=' . $mode . '">'
                . $escape($label) . '</a>';
        }
        $outcome = '';
        if ($result !== null) {
            $success = $result['success'];
            $errors = implode(', ', $result['errors']);
            $outcome = '<section class="result ' . ($success ? 'ok' : 'bad') . '" role="status">'
                . '<strong>' . ($success ? 'VERIFIED — test token accepted.' :
                    'REJECTED — test token was not accepted.') . '</strong><p>'
                . ($result['transport'] ? 'Cloudflare Siteverify API responded.' :
                    'The request or verification transport was unavailable.') . '</p>'
                . ($errors !== '' ? '<small>Diagnostic: ' . $escape($errors) . '</small>' : '')
                . '</section>';
        }
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Turnstile QA · Barama Security</title>'
            . '<style>:root{color-scheme:dark;font-family:system-ui,-apple-system,sans-serif}'
            . '*{box-sizing:border-box}body{background:#0a1322;color:#e9f0ff;margin:0;padding:28px}'
            . 'main{width:min(760px,100%);margin:55px auto}.brand{color:#80a9ff;font-weight:800}'
            . '.back{color:#afc8ff;text-decoration:none;font-size:14px}.muted{color:#a5b6d2}'
            . '.warning{background:#26301f;border:1px solid #8d7636;color:#ffdf8e;padding:12px;'
            . 'border-radius:10px}.panel{background:#121f34;border:1px solid #304363;border-radius:18px;'
            . 'padding:24px;margin-top:24px}.tabs{display:flex;gap:9px;flex-wrap:wrap;margin:20px 0}'
            . '.tab{padding:10px 14px;color:#bcd1ff;border:1px solid #3e5780;'
            . 'border-radius:9px;text-decoration:none}.active{background:#315a9e;color:white}'
            . '.result{padding:20px;border-radius:11px;margin:20px 0}.ok{background:#124236;'
            . 'border:1px solid #2f9475}.bad{background:#48262b;border:1px solid #b64c63}'
            . '.submit{background:#5b89ef;color:white;border:0;border-radius:10px;'
            . 'padding:13px 22px;font-weight:bold;font-size:15px;cursor:pointer;margin-top:20px}'
            . 'small{color:#b6c7df}h1{font-size:32px;margin:13px 0 8px}'
            . '.cf-turnstile{min-height:65px}</style>'
            . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>'
            . '</head><body><main><a class="back" href="'
            . $escape(url('/dashboard/settings/security')) . '">← Back to Security</a>'
            . '<h3 class="brand">▣ Barama Real Estate · Security Center</h3>'
            . '<h1>Cloudflare Turnstile — Platform Test</h1>'
            . '<p class="muted">This page uses your existing Barama admin session, permissions and CSRF defense.'
            . ' Test results come from Cloudflare Siteverify.</p>'
            . '<p class="warning"><strong>Testing keys only.</strong> This page does not enable'
            . ' CAPTCHA on live customer forms or protect against real bots.</p>'
            . '<div class="panel"><strong>Choose verification case</strong><nav class="tabs">'
            . $tabs . '</nav>' . $outcome
            . '<form method="post" action="' . $action . '">' . $csrf
            . '<input type="hidden" name="scenario" value="' . $escape($scenario) . '">'
            . '<div class="cf-turnstile" data-sitekey="' . $siteKey . '"'
            . ' data-action="barama_platform_qa"></div>'
            . '<button type="submit" class="submit">Verify test token</button></form>'
            . '<p class="muted">Scenario: ' . $escape($scenario)
            . '. Cloudflare test results are simulated; no customer data is submitted.</p>'
            . '</div></main></body></html>';
        $csp = "default-src 'none'; script-src https://challenges.cloudflare.com; "
            . "frame-src https://challenges.cloudflare.com; connect-src https://challenges.cloudflare.com; "
            . "img-src 'self' data: https://challenges.cloudflare.com; "
            . "style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'self'";
        return new Response($html, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => $csp,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
