<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\MediaRepository;
use Barama\Support\IdGenerator;
use DomainException;
use finfo;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MediaLibraryUploadService
{
    private const MAX_FILES = 20;
    private const COLLECTION = 'media_library';

    public function capabilities(): array
    {
        $maxBytes = $this->maxBytes();

        return [
            'max_files' => self::MAX_FILES,
            'max_bytes' => $maxBytes,
            'max_label' => $this->formatBytes($maxBytes),
            'accepted_extensions' => [
                'jpg','jpeg','png','webp','gif','pdf','txt','csv','rtf',
                'doc','docx','xls','xlsx','ppt','pptx','odt','ods','odp',
                'mp4','webm','mov','ogv','mp3','wav','m4a','ogg','flac',
            ],
            'groups' => [
                'Images' => ['JPG','PNG','WebP','GIF'],
                'Documents' => ['TXT','CSV','RTF','DOC','DOCX','XLS','XLSX','PPT','PPTX','ODT','ODS','ODP'],
                'PDF' => ['PDF'],
                'Video' => ['MP4','WebM','MOV','OGV'],
                'Music' => ['MP3','WAV','M4A','OGG','FLAC'],
            ],
        ];
    }

    public function storeMany(array $files): array
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null || !$auth->hasRole('administrator') || !$auth->can('media.manage')) {
            throw new DomainException('Administrator Media Library permission is required.');
        }

        $normalized = $this->normalizeFiles($files);
        if ($normalized === []) {
            throw new InvalidArgumentException('Choose at least one media file to upload.');
        }
        if (count($normalized) > self::MAX_FILES) {
            throw new InvalidArgumentException('A maximum of ' . self::MAX_FILES . ' files can be uploaded in one batch.');
        }

        $validated = [];
        foreach ($normalized as $file) {
            $validated[] = $this->validate($file);
        }

        $disk = (new CloudStorageService())->activeDisk();
        $cloud = $disk === 'private' ? null : new CloudObjectStorageService($disk);
        $pdo = Database::connection();
        $repository = new MediaRepository();
        $writtenPaths = [];
        $stored = [];

        try {
            $pdo->beginTransaction();

            foreach ($validated as $file) {
                $relativeDirectory = date('Y/m');
                $filename = str_replace('-', '', IdGenerator::uuid()) . '.' . $file['extension'];
                $relativePath = 'private/' . $relativeDirectory . '/' . $filename;
                $temporaryPath = (string) $file['tmp_name'];
                $checksum = hash_file('sha256', $temporaryPath);
                if (!is_string($checksum)) {
                    throw new RuntimeException('One of the selected media checksums could not be generated.');
                }

                if ($disk === 'private') {
                    $directory = APP_PATH . '/storage/uploads/private/' . $relativeDirectory;
                    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                        throw new RuntimeException('Media Library storage directory could not be created.');
                    }
                    $destination = $directory . '/' . $filename;
                    if (!move_uploaded_file($temporaryPath, $destination)) {
                        throw new RuntimeException('One of the selected media files could not be stored.');
                    }
                    @chmod($destination, 0640);
                    $writtenPaths[] = ['disk' => 'private', 'path' => $destination];
                } else {
                    $cloud->putFile($temporaryPath, $cloud->objectKey($relativePath), (string) $file['mime_type']);
                    $writtenPaths[] = ['disk' => $disk, 'path' => $relativePath];
                }

                $mediaId = $repository->create([
                    'created_by_user_id' => (int) $user['id'],
                    'disk' => $disk,
                    'path' => $relativePath,
                    'original_name' => $file['name'],
                    'mime_type' => $file['mime_type'],
                    'extension' => $file['extension'],
                    'size_bytes' => $file['size_bytes'],
                    'width_px' => $file['width_px'],
                    'height_px' => $file['height_px'],
                    'alt_text' => null,
                    'checksum_sha256' => $checksum,
                ]);

                $repository->attach($mediaId, 'site', 1, self::COLLECTION, 0, false);
                $asset = $repository->asset($mediaId);
                if ($asset === null) {
                    throw new RuntimeException('Stored Media Library asset could not be reloaded.');
                }

                $stored[] = [
                    'id' => $mediaId,
                    'uuid' => (string) $asset['uuid'],
                    'name' => (string) $file['name'],
                    'mime_type' => (string) $file['mime_type'],
                    'category' => (string) $file['category'],
                    'size_bytes' => (int) $file['size_bytes'],
                ];
            }

            $pdo->commit();

            $categoryCounts = [];
            foreach ($stored as $asset) {
                $category = (string) $asset['category'];
                $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            }

            // Asset records and files are already committed. An audit-log outage
            // must not make a successful upload appear rolled back or delete files.
            try {
                (new SecurityAuditService())->record(
                    'admin.media_library_uploaded',
                    (int) $user['id'],
                    null,
                    ['count' => count($stored), 'categories' => $categoryCounts, 'collection' => self::COLLECTION, 'storage_disk' => $disk]
                );
            } catch (Throwable) {
                error_log('Barama Media Library upload completed but its audit event could not be recorded.');
            }

            return ['count' => count($stored), 'assets' => $stored];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach (array_reverse($writtenPaths) as $written) {
                if ($written['disk'] === 'private') {
                    @unlink((string) $written['path']);
                } elseif ($cloud instanceof CloudObjectStorageService) {
                    try {
                        $cloud->delete($cloud->objectKey((string) $written['path']));
                    } catch (Throwable) {
                        error_log('Barama Media Library cloud rollback could not remove an uploaded object.');
                    }
                }
            }
            throw $exception;
        }
    }

    private function validate(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('One of the selected uploads is incomplete or invalid.');
        }

        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        if ($temporaryPath === '' || !is_file($temporaryPath) || !is_uploaded_file($temporaryPath)) {
            throw new InvalidArgumentException('One of the selected media files could not be verified.');
        }

        $actualSize = filesize($temporaryPath);
        if ($actualSize === false) {
            throw new InvalidArgumentException('One of the selected media file sizes could not be verified.');
        }

        $size = (int) $actualSize;
        $maxBytes = $this->maxBytes();
        if ($size < 1 || $size > $maxBytes) {
            throw new InvalidArgumentException('Each media file must be between 1 byte and ' . $this->formatBytes($maxBytes) . '.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower(trim((string) $finfo->file($temporaryPath)));
        $allowed = $this->allowedMimeTypes();
        if (!isset($allowed[$mime])) {
            throw new InvalidArgumentException('Unsupported Media Library file type: ' . ($mime !== '' ? $mime : 'unknown') . '.');
        }

        $definition = $allowed[$mime];
        $width = null;
        $height = null;

        if ($definition['category'] === 'image') {
            $dimensions = @getimagesize($temporaryPath);
            if (!is_array($dimensions) || !isset($dimensions[0], $dimensions[1])) {
                throw new InvalidArgumentException('One of the selected images is invalid.');
            }
            $width = (int) $dimensions[0];
            $height = (int) $dimensions[1];
            $maxPixels = max(1, (int) config('domain.media_max_image_pixels', 50_000_000));
            if ($width < 1 || $height < 1 || ($width * $height) > $maxPixels) {
                throw new InvalidArgumentException('One of the selected image dimensions is not allowed.');
            }
        }

        $name = basename(trim((string) ($file['name'] ?? 'media')));
        $name = mb_substr($name !== '' ? $name : 'media.' . $definition['extension'], 0, 200);

        return [
            'name' => $name,
            'tmp_name' => $temporaryPath,
            'mime_type' => $mime,
            'extension' => $definition['extension'],
            'category' => $definition['category'],
            'size_bytes' => $size,
            'width_px' => $width,
            'height_px' => $height,
        ];
    }

    private function normalizeFiles(array $files): array
    {
        $names = is_array($files['name'] ?? null) ? array_values($files['name']) : [];
        $types = is_array($files['type'] ?? null) ? array_values($files['type']) : [];
        $tmpNames = is_array($files['tmp_name'] ?? null) ? array_values($files['tmp_name']) : [];
        $errors = is_array($files['error'] ?? null) ? array_values($files['error']) : [];
        $sizes = is_array($files['size'] ?? null) ? array_values($files['size']) : [];

        if ($names === [] && isset($files['name'])) {
            $names = [$files['name']];
            $types = [$files['type'] ?? ''];
            $tmpNames = [$files['tmp_name'] ?? ''];
            $errors = [$files['error'] ?? UPLOAD_ERR_NO_FILE];
            $sizes = [$files['size'] ?? 0];
        }

        $normalized = [];
        foreach ($names as $index => $name) {
            $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $normalized[] = [
                'name' => $name,
                'type' => $types[$index] ?? '',
                'tmp_name' => $tmpNames[$index] ?? '',
                'error' => $error,
                'size' => $sizes[$index] ?? 0,
            ];
        }
        return $normalized;
    }

    private function allowedMimeTypes(): array
    {
        return [
            'image/jpeg' => ['extension'=>'jpg','category'=>'image'],
            'image/png' => ['extension'=>'png','category'=>'image'],
            'image/webp' => ['extension'=>'webp','category'=>'image'],
            'image/gif' => ['extension'=>'gif','category'=>'image'],
            'application/pdf' => ['extension'=>'pdf','category'=>'pdf'],
            'text/plain' => ['extension'=>'txt','category'=>'document'],
            'text/csv' => ['extension'=>'csv','category'=>'document'],
            'application/rtf' => ['extension'=>'rtf','category'=>'document'],
            'text/rtf' => ['extension'=>'rtf','category'=>'document'],
            'application/msword' => ['extension'=>'doc','category'=>'document'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['extension'=>'docx','category'=>'document'],
            'application/vnd.ms-excel' => ['extension'=>'xls','category'=>'document'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['extension'=>'xlsx','category'=>'document'],
            'application/vnd.ms-powerpoint' => ['extension'=>'ppt','category'=>'document'],
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['extension'=>'pptx','category'=>'document'],
            'application/vnd.oasis.opendocument.text' => ['extension'=>'odt','category'=>'document'],
            'application/vnd.oasis.opendocument.spreadsheet' => ['extension'=>'ods','category'=>'document'],
            'application/vnd.oasis.opendocument.presentation' => ['extension'=>'odp','category'=>'document'],
            'video/mp4' => ['extension'=>'mp4','category'=>'video'],
            'video/webm' => ['extension'=>'webm','category'=>'video'],
            'video/quicktime' => ['extension'=>'mov','category'=>'video'],
            'video/ogg' => ['extension'=>'ogv','category'=>'video'],
            'audio/mpeg' => ['extension'=>'mp3','category'=>'audio'],
            'audio/wav' => ['extension'=>'wav','category'=>'audio'],
            'audio/x-wav' => ['extension'=>'wav','category'=>'audio'],
            'audio/mp4' => ['extension'=>'m4a','category'=>'audio'],
            'audio/x-m4a' => ['extension'=>'m4a','category'=>'audio'],
            'audio/ogg' => ['extension'=>'ogg','category'=>'audio'],
            'audio/flac' => ['extension'=>'flac','category'=>'audio'],
            'audio/x-flac' => ['extension'=>'flac','category'=>'audio'],
        ];
    }

    private function maxBytes(): int
    {
        return max(1, (int) config('domain.site_media_max_upload_bytes', 50 * 1024 * 1024));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / (1024 * 1024 * 1024), 1), '0'), '.') . ' GB';
        }
        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1), '0'), '.') . ' MB';
    }
}
