<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\CompanyManagementService;
use Barama\Services\DomainWorkflowService;
use Barama\Services\LeadManagementService;
use Barama\Services\ListingManagementService;
use Barama\Services\MediaStorageService;
use Barama\Services\MediaManagementService;
use Barama\Services\MembershipService;
use Barama\Services\ProjectManagementService;
use Barama\Services\PropertyManagementService;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class DeveloperManagementController
{
    public function createCompany(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'company_id' => (new CompanyManagementService())->create($request->all()),
        ], 201);
    }

    public function updateCompany(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            $companyId = $this->id($id);
            (new CompanyManagementService())->update($companyId, $request->all());
            return ['company_id' => $companyId];
        });
    }

    public function addMember(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            $companyId = $this->id($id);
            (new MembershipService())->addByEmail(
                $companyId,
                trim((string) $request->input('email', '')),
                trim((string) $request->input('member_role', 'member'))
            );

            return ['company_id' => $companyId, 'member_added' => true];
        });
    }

    public function deactivateMember(Request $request, string $id, string $userId): Response
    {
        return $this->execute(function () use ($id, $userId): array {
            $companyId = $this->id($id);
            $memberUserId = $this->id($userId);
            (new MembershipService())->deactivate($companyId, $memberUserId);

            return [
                'company_id' => $companyId,
                'user_id' => $memberUserId,
                'member_deactivated' => true,
            ];
        });
    }

    public function createProject(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'project_id' => (new ProjectManagementService())->create($request->all()),
        ], 201);
    }

    public function updateProject(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            $projectId = $this->id($id);
            (new ProjectManagementService())->update($projectId, $request->all());
            return ['project_id' => $projectId];
        });
    }

    public function createProperty(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'property_id' => (new PropertyManagementService())->create($request->all()),
        ], 201);
    }

    public function updateProperty(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            $propertyId = $this->id($id);
            (new PropertyManagementService())->update($propertyId, $request->all());
            return ['property_id' => $propertyId];
        });
    }

    public function createListing(Request $request): Response
    {
        return $this->execute(fn (): array => [
            'listing_id' => (new ListingManagementService())->create($request->all()),
        ], 201);
    }

    public function updateListing(Request $request, string $id): Response
    {
        return $this->execute(function () use ($request, $id): array {
            $listingId = $this->id($id);
            (new ListingManagementService())->update($listingId, $request->all());
            return ['listing_id' => $listingId];
        });
    }

    public function workflow(Request $request, string $resource, string $id): Response
    {
        return $this->execute(function () use ($request, $resource, $id): array {
            $entityId = $this->id($id);
            $status = trim((string) $request->input('status', ''));
            $note = trim((string) $request->input('note', ''));
            (new DomainWorkflowService())->transition(
                $resource,
                $entityId,
                $status,
                $note !== '' ? $note : null
            );

            return [
                'resource' => $resource,
                'id' => $entityId,
                'status' => $status,
            ];
        });
    }

    public function convertLead(Request $request, string $id): Response
    {
        return $this->execute(fn (): array => [
            'lead_id' => (new LeadManagementService())->convertInquiry(
                $this->id($id),
                $request->all()
            ),
        ], 201);
    }

    public function setPrimaryMedia(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            $attachmentId = $this->id($id);
            (new MediaManagementService())->setPrimary($attachmentId);

            return [
                'attachment_id' => $attachmentId,
                'primary' => true,
            ];
        });
    }

    public function removeMedia(Request $request, string $id): Response
    {
        return $this->execute(function () use ($id): array {
            $attachmentId = $this->id($id);
            (new MediaManagementService())->remove($attachmentId);

            return [
                'attachment_id' => $attachmentId,
                'removed' => true,
            ];
        });
    }

    public function uploadMedia(Request $request): Response
    {
        return $this->execute(function () use ($request): array {
            $files = $request->files();
            $file = $files['file'] ?? null;

            if (!is_array($file)) {
                throw new InvalidArgumentException('A file upload is required.');
            }

            return [
                'media_id' => (new MediaStorageService())->storeAndAttach(
                    $file,
                    $request->all()
                ),
            ];
        }, 201);
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
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    private function id(string $value): int
    {
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new InvalidArgumentException('A valid resource ID is required.');
        }

        return (int) $value;
    }
}
