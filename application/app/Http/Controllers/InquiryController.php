<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\InquiryService;
use DomainException;
use InvalidArgumentException;

final class InquiryController
{
    public function store(Request $request): Response
    {
        try {
            $user = auth_user();
            $inquiryId = (new InquiryService())->capture(
                $request->all(),
                $request->ip(),
                $user !== null ? (int) $user['id'] : null
            );

            return Response::json([
                'ok' => true,
                'data' => [
                    'inquiry_id' => $inquiryId,
                    'message' => 'Your inquiry has been submitted.',
                ],
            ], 201);
        } catch (InvalidArgumentException $exception) {
            return Response::json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (DomainException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'too many')
                ? 429
                : 403;

            return Response::json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], $status);
        }
    }
}
