<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\RolePermissionGovernanceService;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class RolePermissionController
{
    public function update(Request $request, string $role): Response
    {
        try {
            (new RolePermissionGovernanceService())->update($role, $request->all());

            return Response::redirect(
                '/dashboard/roles-permissions?saved=1&role=' . rawurlencode($role)
            );
        } catch (InvalidArgumentException|DomainException $exception) {
            return Response::redirect(
                '/dashboard/roles-permissions?error=' . rawurlencode($exception->getMessage())
                . '&role=' . rawurlencode($role)
            );
        } catch (Throwable $exception) {
            error_log('[Barama Roles] ' . $exception::class . ': ' . $exception->getMessage());

            return Response::redirect(
                '/dashboard/roles-permissions?error=' . rawurlencode('Role permissions could not be saved. Review the server error log and retry.')
                . '&role=' . rawurlencode($role)
            );
        }
    }
}
