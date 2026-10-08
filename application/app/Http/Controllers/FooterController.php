<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Repositories\FooterContentRepository;
use Barama\Services\FooterContentService;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class FooterController
{
    public function index(Request $request): Response
    {
        $settings = (new FooterContentRepository())->get();

        return Response::html(View::render('workspace/footer', [
            'pageTitle' => 'Footer · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => (new DashboardController())->policyCenterNavigation(),
            'activeSection' => 'footer',
            'footerSettings' => $settings,
            'footerSaved' => $request->input('saved', '') === '1',
            'footerError' => (string) $request->input('error', ''),
        ]));
    }

    public function update(Request $request): Response
    {
        $ajax = strtolower(trim((string) $request->header('X-Requested-With', '')))
            === 'xmlhttprequest'
            || str_contains(strtolower((string) $request->header('Accept', '')), 'application/json');

        try {
            (new FooterContentService())->update($request->all());

            if ($ajax) {
                return Response::json([
                    'ok' => true,
                    'data' => ['message' => 'Footer content saved successfully.'],
                ]);
            }

            return Response::redirect(url('/dashboard/footer?saved=1'));
        } catch (InvalidArgumentException|DomainException $exception) {
            if ($ajax) {
                return Response::json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ], $exception instanceof DomainException ? 403 : 422);
            }
            return Response::redirect(
                url('/dashboard/footer?error=' . rawurlencode($exception->getMessage()))
            );
        } catch (Throwable $exception) {
            error_log('[Barama Footer] ' . $exception::class);
            $message = 'Unable to save footer settings. Please retry.';
            if ($ajax) {
                return Response::json(['ok' => false, 'message' => $message], 500);
            }
            return Response::redirect(
                url('/dashboard/footer?error=' . rawurlencode($message))
            );
        }
    }
}
