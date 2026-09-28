<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\BuiltInPageManagementService;
use Barama\Services\ContactMessageService;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class BuiltInPageAdminController
{
    public function update(Request $request, string $page, string $section): Response
    {
        return $this->execute(function () use ($request, $page, $section): array {
            (new BuiltInPageManagementService())->updateSection(
                $page,
                $section,
                $request->all()
            );

            return [
                'page' => $page,
                'section' => $section,
                'message' => ucfirst($page) . ' configuration updated.',
            ];
        });
    }

    public function toggle(Request $request, string $page, string $section): Response
    {
        return $this->execute(function () use ($request, $page, $section): array {
            $enabled = in_array(
                strtolower(trim((string) $request->input('enabled', '0'))),
                ['1', 'true', 'on', 'yes'],
                true
            );

            (new BuiltInPageManagementService())->toggleSection(
                $page,
                $section,
                $enabled
            );

            return [
                'page' => $page,
                'section' => $section,
                'enabled' => $enabled,
                'message' => ucfirst($page) . ' section visibility updated.',
            ];
        });
    }

    public function contactMessageStatus(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid contact message ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            (new ContactMessageService())->setStatus((int) $id, $status);

            return [
                'contact_message_id' => (int) $id,
                'status' => $status,
                'message' => 'Contact message status updated.',
            ];
        });
    }

    /** @param callable():array<string,mixed> $operation */
    private function execute(callable $operation, int $successStatus = 200): Response
    {
        try {
            return Response::json([
                'ok' => true,
                'data' => $operation(),
            ], $successStatus);
        } catch (InvalidArgumentException $exception) {
            return Response::json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (DomainException $exception) {
            return Response::json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 403);
        } catch (Throwable) {
            return Response::json([
                'ok' => false,
                'message' => 'The request could not be completed.',
            ], 500);
        }
    }
}
