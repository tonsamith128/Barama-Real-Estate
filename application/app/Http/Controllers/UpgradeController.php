<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Csrf;
use Barama\Core\Installation;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Services\UpgradeService;
use RuntimeException;
use Throwable;

final class UpgradeController
{
    public function handle(): void
    {
        $request = Request::capture();
        $service = new UpgradeService();

        if (!Installation::isInstalled()) {
            $this->redirect($service->basePath() . '/installer/');
        }

        if ($request->method() === 'POST') {
            if (!Csrf::validate((string) $request->input('_token', ''))) {
                $this->render(
                    $service,
                    null,
                    'Your upgrade session expired. Refresh the page and try again.',
                    419
                );
                return;
            }

            try {
                $result = $service->upgrade([
                    'admin_email' => $request->input('admin_email', ''),
                    'admin_password' => $request->input('admin_password', ''),
                    'backup_confirmed' => $request->input('backup_confirmed', ''),
                    'ip' => $request->ip(),
                    'user_agent' => $request->header('User-Agent', ''),
                ]);

                $this->render($service, $result, null);
                return;
            } catch (RuntimeException $exception) {
                $this->render($service, null, $exception->getMessage(), 422);
                return;
            } catch (Throwable) {
                $this->render(
                    $service,
                    null,
                    'The upgrade could not be completed. Restore from your backup if a database migration was interrupted, then contact support.',
                    500
                );
                return;
            }
        }

        $this->render($service, null, null);
    }

    /** @param array<string,mixed>|null $result */
    private function render(
        UpgradeService $service,
        ?array $result,
        ?string $error,
        int $status = 200
    ): void {
        $state = $service->status();

        $html = View::render('updater/index', [
            'pageTitle' => 'Upgrade Barama Real Estate',
            'basePath' => $service->basePath(),
            'updaterUrl' => $service->updaterUrl(),
            'state' => $state,
            'result' => $result,
            'error' => $error,
        ], null);

        (new Response($html, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]))->send();
    }

    private function redirect(string $location): never
    {
        (new Response('', 302, [
            'Location' => $location,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]))->send();

        exit;
    }
}
