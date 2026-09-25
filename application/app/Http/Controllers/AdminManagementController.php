<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\AdminMediaManagementService;
use Barama\Services\BulkModerationService;
use Barama\Services\CatalogManagementService;
use Barama\Services\DomainWorkflowService;
use Barama\Services\UserAdministrationService;
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

    public function updateLocationStatus(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid location ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            (new CatalogManagementService())->setLocationStatus((int) $id, $status);

            return [
                'location_id' => (int) $id,
                'status' => $status,
                'message' => 'Location status updated.',
            ];
        });
    }

    public function updateAmenityStatus(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid amenity ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            (new CatalogManagementService())->setAmenityStatus((int) $id, $status);

            return [
                'amenity_id' => (int) $id,
                'status' => $status,
                'message' => 'Amenity status updated.',
            ];
        });
    }

    public function deleteOrphanMedia(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid media asset ID is required.');
            }

            (new AdminMediaManagementService())->deleteOrphanAsset((int) $id);

            return [
                'media_id' => (int) $id,
                'message' => 'Orphaned media asset deleted.',
            ];
        });
    }

    public function bulkWorkflow(Request $request): Response
    {
        return $this->execute(function () use ($request): array {
            $records = $request->input('records', []);

            if (!is_array($records)) {
                throw new InvalidArgumentException('Selected moderation records are invalid.');
            }

            $decision = trim((string) $request->input('decision', ''));
            $note = trim((string) $request->input('note', ''));

            $result = (new BulkModerationService())->moderate(
                $records,
                $decision,
                $note !== '' ? mb_substr($note, 0, 500) : null
            );

            $result['message'] = sprintf(
                'Bulk moderation processed %d record(s): %d succeeded, %d failed.',
                $result['processed'],
                $result['succeeded'],
                $result['failed']
            );

            return $result;
        });
    }

    public function workflow(Request $request, string $resource, string $id): Response
    {
        return $this->execute(function () use ($request, $resource, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid resource ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            $note = trim((string) $request->input('note', ''));

            if ($status === 'rejected' && $note === '') {
                throw new InvalidArgumentException('A rejection reason is required before returning this record to the submitter.');
            }

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

    public function updateUserStatus(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid user ID is required.');
            }

            $status = trim((string) $request->input('status', ''));
            (new UserAdministrationService())->setStatus((int) $id, $status);

            return [
                'user_id' => (int) $id,
                'status' => $status,
                'message' => 'User account status updated.',
            ];
        });
    }

    public function setAdministrator(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid user ID is required.');
            }

            $enabled = in_array(
                (string) $request->input('enabled', ''),
                ['1', 'true', 'on'],
                true
            );

            (new UserAdministrationService())->setAdministrator((int) $id, $enabled);

            return [
                'user_id' => (int) $id,
                'administrator' => $enabled,
                'message' => $enabled
                    ? 'Administrator role granted.'
                    : 'Administrator role removed.',
            ];
        });
    }

    public function revokeUserSessions(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('A valid user ID is required.');
            }

            (new UserAdministrationService())->revokeSessions((int) $id);

            return [
                'user_id' => (int) $id,
                'message' => 'Active sessions were invalidated for this account.',
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
