<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Repositories\AppearanceRepository;
use Barama\Services\AppearanceManagementService;

final class AppearanceController
{
    public function stylesheet(Request $request): Response
    {
        $repository = new AppearanceRepository();
        $config = $repository->get();
        $version = AppearanceRepository::version($config);
        $css = (new AppearanceManagementService())->stylesheet($config);

        return Response::binary(
            $css,
            'text/css; charset=UTF-8',
            [
                'Cache-Control' => 'public, max-age=300, stale-while-revalidate=60',
                'ETag' => '"' . $version . '"',
            ]
        );
    }
}
