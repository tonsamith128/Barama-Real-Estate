<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\CatalogManagementService;
use Barama\Services\DomainWorkflowService;
use DomainException;
use InvalidArgumentException;

final class AdminManagementController
{
    public function createLocation(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'location_id' => (new CatalogManagementService())->createLocation($request->all()),
        ], 201);
    }

    public function createAmenity(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'amenity_id' => (new CatalogManagementService())->createAmenity($request->all()),
        ], 201);
    }

    public function workflow(Request $request, string $resource, string $id): Response
    {
        return $this->execute(function () use ($request, $resource, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid resource ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            $note = trim((string) $request->input('note', ''));

            (new DomainWorkflowService())->transition(
                $resource,
                (int) $id,
                $status,
                $note !== '' ? $note : null
            );

            return [
                'resource' => $resource,
                'id' => (int) $id,
                'status' => $status,
            ];
        });
    }

    /**
     * @param callable(): array<string, mixed> $action
     */
    private function execute(callable $action, int $successStatus = 200): Response
    {
        try {
            return Response::json([
                'ok' => true,
                'data' => $action(),
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
        }
    }
}
