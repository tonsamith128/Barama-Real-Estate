<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\NewsletterSubscriptionService;
use DomainException;
use InvalidArgumentException;

final class NewsletterController
{
    public function store(Request $request): Response
    {
        try {
            (new NewsletterSubscriptionService())->subscribe(
                $request->all(),
                $request->ip()
            );

            return Response::json([
                'ok' => true,
                'data' => [
                    'message' => 'Subscription saved successfully.',
                ],
            ], 201);
        } catch (InvalidArgumentException $exception) {
            return Response::json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (DomainException $exception) {
            return Response::json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 429);
        }
    }
}
