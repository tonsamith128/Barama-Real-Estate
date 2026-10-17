<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\MediaRepository;
use Barama\Repositories\WorkflowRepository;
use Barama\Support\DomainInput;
use Barama\Support\IdGenerator;
use DomainException;
use finfo;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MediaStorageService
{
    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $input
     */
    public function storeAndAttach(array $file, array $input): int
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null || !$auth->can('media.manage')) {
            throw new DomainException('Media management permission is required.');
        }

        $entityType = DomainInput::requiredString($input, 'entity_type', 32);
        if (!in_array($entityType, DomainRules::MEDIA_ENTITY_TYPES, true)) {
            throw new InvalidArgumentException('Invalid media entity type.');
        }

        $entityId = DomainInput::requiredPositiveInt($input, 'entity_id');
        $context = (new WorkflowRepository())->context($entityType, $entityId);
        if ($context === null || $context['company_id'] === null) {
            throw new InvalidArgumentException('Media target was not found.');
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
                'Reviewed or published records are locked. Return the target to an editable review state before changing its media.'
            );
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The uploaded file is incomplete or invalid.');
        }

        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        if (
            $temporaryPath === ''
            || !is_file($temporaryPath)
            || (!defined('BARAMA_CLI') && !is_uploaded_file($temporaryPath))
        ) {
            throw new InvalidArgumentException('The uploaded file could not be verified.');
        }

        $actualSize = filesize($temporaryPath);
        if ($actualSize === false) {
            throw new InvalidArgumentException('The uploaded file size could not be verified.');
        }

        $size = (int) $actualSize;
        $maxBytes = max(1, (int) config('domain.media_max_upload_bytes', 10 * 1024 * 1024));
        if ($size < 1 || $size > $maxBytes) {
            throw new InvalidArgumentException('The uploaded file exceeds the allowed size.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($temporaryPath);
        $allowed = (array) config('domain.media_allowed_mime_types', []);
        if (!isset($allowed[$mime])) {
            throw new InvalidArgumentException('This file type is not allowed.');
        }

        $extension = (string) $allowed[$mime];
        $width = null;
        $height = null;

        if (str_starts_with($mime, 'image/')) {
            $dimensions = @getimagesize($temporaryPath);
            if (!is_array($dimensions) || !isset($dimensions[0], $dimensions[1])) {
                throw new InvalidArgumentException('The uploaded image is invalid.');
            }
            $width = (int) $dimensions[0];
            $height = (int) $dimensions[1];
            $maxPixels = max(1, (int) config('domain.media_max_image_pixels', 50_000_000));
            if ($width < 1 || $height < 1 || ($width * $height) > $maxPixels) {
                throw new InvalidArgumentException('The uploaded image dimensions are not allowed.');
            }
        }

        $relativeDirectory = date('Y/m');
        $filename = str_replace('-', '', IdGenerator::uuid()) . '.' . $extension;
        $relativePath = 'private/' . $relativeDirectory . '/' . $filename;
        $checksum = hash_file('sha256', $temporaryPath);
        if (!is_string($checksum)) {
            throw new RuntimeException('The uploaded file checksum could not be generated.');
        }

        $disk = (new CloudStorageService())->activeDisk();
        $cloud = null;
        $destination = null;

        if ($disk === 'private') {
            $directory = APP_PATH . '/storage/uploads/private/' . $relativeDirectory;
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Media storage directory could not be created.');
            }
            $destination = $directory . '/' . $filename;
            if (defined('BARAMA_CLI')) {
                $moved = copy($temporaryPath, $destination);
                if ($moved) {
                    @unlink($temporaryPath);
                }
            } else {
                $moved = move_uploaded_file($temporaryPath, $destination);
            }
            if (!$moved) {
                throw new RuntimeException('The uploaded file could not be stored.');
            }
            @chmod($destination, 0640);
        } else {
            $cloud = new CloudObjectStorageService($disk);
            $cloud->putFile($temporaryPath, $cloud->objectKey($relativePath), $mime);
            if (defined('BARAMA_CLI')) {
                @unlink($temporaryPath);
            }
        }

        $repository = new MediaRepository();
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            $mediaId = $repository->create([
                'created_by_user_id' => (int) $user['id'],
                'disk' => $disk,
                'path' => $relativePath,
                'original_name' => basename((string) ($file['name'] ?? 'upload.' . $extension)),
                'mime_type' => $mime,
                'extension' => $extension,
                'size_bytes' => $size,
                'width_px' => $width,
                'height_px' => $height,
                'alt_text' => DomainInput::optionalString($input, 'alt_text', 255),
                'checksum_sha256' => $checksum,
            ]);
            $repository->attach(
                $mediaId,
                $entityType,
                $entityId,
                DomainInput::optionalString($input, 'collection', 40) ?? 'gallery',
                (int) ($input['position'] ?? 0),
                DomainInput::boolean($input, 'is_primary')
            );
            if ($approvedCompanyAdmin) {
                (new SecurityAuditService())->record(
                    'admin.approved_company_media_uploaded',
                    (int) $user['id'],
                    null,
                    ['company_id' => $entityId, 'media_id' => $mediaId]
                );
            }
            $pdo->commit();
            return $mediaId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($disk === 'private' && is_string($destination)) {
                @unlink($destination);
            } elseif ($cloud instanceof CloudObjectStorageService) {
                try {
                    $cloud->delete($cloud->objectKey($relativePath));
                } catch (Throwable) {
                    error_log('Barama cloud rollback could not remove object: ' . $relativePath);
                }
            }
            throw $exception;
        }
    }
}
