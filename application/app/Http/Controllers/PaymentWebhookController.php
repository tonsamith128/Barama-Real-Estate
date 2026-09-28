<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\PaymentWebhookService;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class PaymentWebhookController
{
    public function receive(Request $request, string $provider): Response
    {
        $token = trim((string) $request->header('X-Barama-Webhook-Token', ''));

        try {
            $result = (new PaymentWebhookService())->receive(
                $provider,
                $request->all(),
                $token
            );

            return Response::json([
                'ok' => true,
                'data' => $result,
            ]);
        } catch (InvalidArgumentException $exception) {
            return Response::json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (DomainException $exception) {
            $status = (int) $exception->getCode();

            if (!in_array($status, [401, 409], true)) {
                $status = 403;
            }

            return Response::json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], $status);
        } catch (Throwable) {
            return Response::json([
                'ok' => false,
                'message' => 'Webhook processing failed.',
            ], 500);
        }
    }
}
