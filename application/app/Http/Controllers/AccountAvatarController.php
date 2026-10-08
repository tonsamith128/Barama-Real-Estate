<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Repositories\AccountSettingsRepository;

final class AccountAvatarController
{
    public function show(Request $request, string $uuid): Response
    {
        $user = auth_user();
        if ($user === null) {
            return Response::redirect(url('/login'));
        }

        if (
            preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                $uuid
            ) !== 1
        ) {
            return Response::html('', 404);
        }

        $media = (new AccountSettingsRepository())->avatarMediaForUser(
            (int) $user['id'],
            $uuid
        );

        if ($media === null) {
            return Response::html('', 404);
        }

        $mimeType = (string) ($media['mime_type'] ?? '');
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return Response::html('', 404);
        }

        $storageRoot = realpath(APP_PATH . '/storage/uploads');
        $path = realpath(
            APP_PATH . '/storage/uploads/' . ltrim((string) ($media['path'] ?? ''), '/')
        );

        if (
            $storageRoot === false
            || $path === false
            || !str_starts_with($path, $storageRoot . DIRECTORY_SEPARATOR)
            || !is_file($path)
        ) {
            return Response::html('', 404);
        }

        $content = file_get_contents($path);
        if (!is_string($content) || $content === '') {
            return Response::html('', 404);
        }

        return Response::binary(
            $content,
            $mimeType,
            [
                'Cache-Control' => 'private, max-age=3600',
                'Content-Disposition' => 'inline; filename="' .
                    rawurlencode((string) ($media['original_name'] ?? 'profile-avatar')) . '"',
                'Content-Length' => (string) strlen($content),
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
