<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Repositories\SiteContentRepository;
use Barama\Services\ContactMessageService;
use DomainException;
use InvalidArgumentException;

final class ContactMessageController
{
    public function store(Request $request): Response
    {
        try {
            $user = auth_user();
            $id = (new ContactMessageService())->capture(
                $request->all(),
                $request->ip(),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                $user !== null ? (int) $user['id'] : null
            );

            $config = (new SiteContentRepository())->get();
            $form = is_array($config['contact']['sections']['form'] ?? null)
                ? $config['contact']['sections']['form']
                : [];
            $success = trim((string) ($form['success_message'] ?? ''));

            return Response::json([
                'ok' => true,
                'data' => [
                    'contact_message_id' => $id,
                    'message' => $success !== ''
                        ? $success
                        : 'Thank you. Your message has been received.',
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
