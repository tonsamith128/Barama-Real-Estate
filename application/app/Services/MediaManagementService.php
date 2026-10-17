<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\MediaRepository;
use Barama\Repositories\WorkflowRepository;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MediaManagementService
{
    public function updateAltText(int $attachmentId, ?string $altText): void
    {
        $media = new MediaRepository();
        $attachment = $this->manageableAttachment($media, $attachmentId);

        $altText = $altText !== null ? trim($altText) : null;

        if ($altText === '') {
            $altText = null;
        }

        if ($altText !== null && mb_strlen($altText) > 255) {
            throw new InvalidArgumentException('Alt text cannot exceed 255 characters.');
        }

        $media->updateAltText(
            (int) $attachment['media_asset_id'],
            $altText
        );
        $this->auditApprovedCompanyEdit($attachment, 'admin.approved_company_media_alt_updated');
    }

    public function setPrimary(int $attachmentId): void
    {
        $media = new MediaRepository();
        $attachment = $this->manageableAttachment($media, $attachmentId);

        if (!str_starts_with((string) $attachment['mime_type'], 'image/')) {
            throw new DomainException('Only image media can be used as a primary gallery item.');
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            $media->setPrimary($attachmentId);
            $this->auditApprovedCompanyEdit($attachment, 'admin.approved_company_media_primary_updated');
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function remove(int $attachmentId): void
    {
        $media = new MediaRepository();
        $attachment = $this->manageableAttachment($media, $attachmentId);
        $mediaId = (int) $attachment['media_asset_id'];
        $path = (string) $attachment['path'];
        $deleteAsset = false;
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            $media->deleteAttachment($attachmentId);

            if ($media->attachmentCountForMedia($mediaId) === 0) {
                $media->deleteAsset($mediaId);
                $deleteAsset = true;
            }

            $this->auditApprovedCompanyEdit($attachment, 'admin.approved_company_media_removed');
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        if ($deleteAsset) {
            $storageRoot = realpath(APP_PATH . '/storage/uploads');
            $absolute = realpath(APP_PATH . '/storage/uploads/' . ltrim($path, '/'));

            if (
                $storageRoot !== false
                && $absolute !== false
                && str_starts_with($absolute, $storageRoot . DIRECTORY_SEPARATOR)
                && is_file($absolute)
                && !@unlink($absolute)
            ) {
                error_log('Barama media cleanup could not remove: ' . $absolute);
            }
        }
    }

    /** @return array<string,mixed> */
    private function manageableAttachment(MediaRepository $media, int $attachmentId): array
    {
        $auth = new AuthService();

        if (!$auth->can('media.manage')) {
            throw new DomainException('Media management permission is required.');
        }

        $attachment = $media->attachment($attachmentId);

        if ($attachment === null) {
            throw new InvalidArgumentException('Media attachment was not found.');
        }

        $entityType = (string) $attachment['entity_type'];

        if (!in_array($entityType, ['company', 'project', 'property', 'listing'], true)) {
            throw new InvalidArgumentException('Media attachment target is invalid.');
        }

        $context = (new WorkflowRepository())->context(
            $entityType,
            (int) $attachment['entity_id']
        );

        if ($context === null || $context['company_id'] === null) {
            throw new InvalidArgumentException('Media attachment target was not found.');
        }

        (new CompanyAccessService())->assertCanManage((int) $context['company_id']);

        $approvedCompanyAdmin = ApprovedCompanyMediaEditPolicy::allows(
            $entityType,
            (string) $context['status'],
            $auth->hasRole('administrator'),
            $auth->can('dashboard.access'),
            $auth->can('company.manage'),
            $auth->can('media.manage')
        );

        if (!in_array((string) $context['status'], ['draft', 'rejected'], true) && !$approvedCompanyAdmin) {
            throw new DomainException(
                'Media on reviewed or published records is locked. Return the record to an editable review state before changing its gallery.'
            );
        }

        if ($approvedCompanyAdmin) {
            $attachment['_approved_company_admin'] = true;
            $attachment['_audit_actor_id'] = (int) ($auth->user()['id'] ?? 0);
        }

        return $attachment;
    }

    /** @param array<string,mixed> $attachment */
    private function auditApprovedCompanyEdit(array $attachment, string $event): void
    {
        if (empty($attachment['_approved_company_admin'])) {
            return;
        }

        (new SecurityAuditService())->record(
            $event,
            (int) $attachment['_audit_actor_id'],
            null,
            [
                'company_id' => (int) $attachment['entity_id'],
                'media_id' => (int) $attachment['media_asset_id'],
                'attachment_id' => (int) $attachment['id'],
            ]
        );
    }
}
