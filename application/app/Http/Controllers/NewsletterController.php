<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\NewsletterSubscriptionService;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class NewsletterController
{
    public function store(Request $request): Response
    {
        $ajax = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')))
            === 'xmlhttprequest';

        try {
            (new NewsletterSubscriptionService())->subscribe(
                $request->all(),
                $request->ip()
            );

            if ($ajax) {
                return Response::json([
                    'ok' => true,
                    'data' => [
                        'message' => 'Subscription saved successfully.',
                    ],
                ], 201);
            }

            return Response::redirect('/?subscribed=1#subscribe');
        } catch (InvalidArgumentException $exception) {
            if ($ajax) {
                return Response::json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return Response::redirect(
                '/?subscribe_error=' . rawurlencode($exception->getMessage()) . '#subscribe'
            );
        } catch (DomainException $exception) {
            if ($ajax) {
                return Response::json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ], 429);
            }

            return Response::redirect(
                '/?subscribe_error=' . rawurlencode($exception->getMessage()) . '#subscribe'
            );
        } catch (Throwable $exception) {
            error_log(
                '[Barama Newsletter] '
                . $exception::class
                . ': '
                . $exception->getMessage()
            );

            $message = 'Subscription could not be completed. Please try again.';

            if ($ajax) {
                return Response::json([
                    'ok' => false,
                    'message' => $message,
                ], 500);
            }

            return Response::redirect(
                '/?subscribe_error=' . rawurlencode($message) . '#subscribe'
            );
        }
    }
}
