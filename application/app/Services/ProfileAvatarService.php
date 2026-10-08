<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\AccountSettingsRepository;
use Barama\Repositories\MediaRepository;
use InvalidArgumentException;
use RuntimeException;

final class ProfileAvatarService
{
    private const MAX_BYTES = 5242880;
    private const MAX_PIXELS = 20000000;

    /** @var array<string,string> */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @param array<string,mixed> $file @return array<string,mixed> */
    public function replace(int $userId, array $file): array
    {
        $validated = $this->validateUpload($file);
        $profileRepo = new AccountSettingsRepository();
        $mediaRepo = new MediaRepository();
        $profile = $profileRepo->profile($userId);
        $oldMediaId = isset($profile['avatar_media_id']) && $profile['avatar_media_id'] !== null
            ? (int) $profile['avatar_media_id']
            : null;

        $relativeDir = 'private/avatars/' . date('Y/m');
        $absoluteDir = APP_PATH . '/storage/uploads/' . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0750, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Profile avatar storage could not be prepared.');
        }

        $storedName = bin2hex(random_bytes(20)) . '.' . $validated['extension'];
        $absolutePath = $absoluteDir . '/' . $storedName;
        $relativePath = $relativeDir . '/' . $storedName;

        if (!move_uploaded_file((string) $file['tmp_name'], $absolutePath)) {
            throw new RuntimeException('Profile avatar could not be stored.');
        }

        @chmod($absolutePath, 0640);
        $checksum = hash_file('sha256', $absolutePath);

        if (!is_string($checksum) || $checksum === '') {
            @unlink($absolutePath);
            throw new RuntimeException('Profile avatar checksum could not be created.');
        }

        $mediaId = 0;

        try {
            $mediaId = $mediaRepo->create([
                'created_by_user_id' => $userId,
                'path' => $relativePath,
                'original_name' => $validated['original_name'],
                'mime_type' => $validated['mime_type'],
                'extension' => $validated['extension'],
                'size_bytes' => $validated['size_bytes'],
                'width_px' => $validated['width_px'],
                'height_px' => $validated['height_px'],
                'alt_text' => 'Profile avatar',
                'checksum_sha256' => $checksum,
            ]);

            $profileRepo->setAvatarMediaId($userId, $mediaId);
        } catch (\Throwable $exception) {
            if ($mediaId > 0) {
                try {
                    $mediaRepo->deleteAsset($mediaId);
                } catch (\Throwable) {
                }
            }
            @unlink($absolutePath);
            throw $exception;
        }

        if ($oldMediaId !== null && $oldMediaId > 0 && $oldMediaId !== $mediaId) {
            $this->removeOwnedAsset($userId, $oldMediaId);
        }

        $asset = $mediaRepo->asset($mediaId);
        if (!is_array($asset)) {
            throw new RuntimeException('Profile avatar metadata could not be loaded.');
        }

        return $asset;
    }

    public function remove(int $userId): void
    {
        $profileRepo = new AccountSettingsRepository();
        $profile = $profileRepo->profile($userId);
        $oldMediaId = isset($profile['avatar_media_id']) && $profile['avatar_media_id'] !== null
            ? (int) $profile['avatar_media_id']
            : null;

        $profileRepo->setAvatarMediaId($userId, null);

        if ($oldMediaId !== null && $oldMediaId > 0) {
            $this->removeOwnedAsset($userId, $oldMediaId);
        }
    }

    /** @param array<string,mixed> $file @return array<string,mixed> */
    private function validateUpload(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('Choose a profile avatar to upload.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Profile avatar upload did not complete successfully.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('Profile avatar upload is invalid.');
        }

        $size = filesize($tmp);
        if ($size === false || $size < 1) {
            throw new InvalidArgumentException('Profile avatar is empty.');
        }
        if ($size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Profile avatar must be 5 MB or smaller.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        if (!isset(self::EXTENSIONS[$mime])) {
            throw new InvalidArgumentException('Profile avatar must be a JPG, PNG, or WebP image.');
        }

        $image = @getimagesize($tmp);
        if (!is_array($image)) {
            throw new InvalidArgumentException('Profile avatar must be a valid image.');
        }

        $width = (int) ($image[0] ?? 0);
        $height = (int) ($image[1] ?? 0);
        if ($width < 1 || $height < 1 || ($width * $height) > self::MAX_PIXELS) {
            throw new InvalidArgumentException('Profile avatar dimensions are not supported.');
        }

        return [
            'original_name' => mb_substr(trim((string) ($file['name'] ?? 'profile-avatar')), 0, 255),
            'mime_type' => $mime,
            'extension' => self::EXTENSIONS[$mime],
            'size_bytes' => (int) $size,
            'width_px' => $width,
            'height_px' => $height,
        ];
    }

    private function removeOwnedAsset(int $userId, int $mediaId): void
    {
        $mediaRepo = new MediaRepository();
        $asset = $mediaRepo->asset($mediaId);

        if (!is_array($asset) || (int) ($asset['created_by_user_id'] ?? 0) !== $userId) {
            return;
        }

        if ($mediaRepo->attachmentCountForMedia($mediaId) > 0) {
            return;
        }

        $storageRoot = realpath(APP_PATH . '/storage/uploads');
        $absolute = realpath(APP_PATH . '/storage/uploads/' . ltrim((string) ($asset['path'] ?? ''), '/'));

        if (
            $storageRoot !== false
            && $absolute !== false
            && str_starts_with($absolute, $storageRoot . DIRECTORY_SEPARATOR)
            && is_file($absolute)
        ) {
            @unlink($absolute);
        }

        try {
            $mediaRepo->deleteAsset($mediaId);
        } catch (\Throwable) {
        }
    }
}
