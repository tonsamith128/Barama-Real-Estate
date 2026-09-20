<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Repositories\AccountSettingsRepository;
use Barama\Support\Locale;

final class LocaleController
{
    public function update(Request $request): Response
    {
        $normalized = Locale::normalize((string) $request->input('locale', ''));

        if ($normalized !== null) {
            Locale::set($normalized);
            $user = auth_user();

            if ($user !== null) {
                (new AccountSettingsRepository())->savePreferredLanguage(
                    (int) $user['id'],
                    $normalized
                );
            }
        }

        $returnPath = trim((string) $request->input('return', '/'));

        if (
            $returnPath === ''
            || !str_starts_with($returnPath, '/')
            || str_starts_with($returnPath, '//')
            || parse_url($returnPath, PHP_URL_HOST) !== null
        ) {
            $returnPath = '/';
        }

        return Response::redirect(url($returnPath));
    }
}
