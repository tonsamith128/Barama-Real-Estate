<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\LocationProviderService;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

final class LocationProviderController
{
    public function suggest(Request $request): Response
    {
        $user=auth_user();
        if ($user===null) return Response::json(['ok'=>false,'message'=>'Authentication required.'],401);
        try {
            $results=(new LocationProviderService())->search(
                (string)$request->input('q',''),
                (string)$request->input('country',''),
                (int)$user['id']
            );
            // Never return access tokens or unfiltered provider response objects.
            return Response::json(['ok'=>true,'provider'=>'mapbox','temporary'=>true,'results'=>$results]);
        } catch (DomainException|InvalidArgumentException $error) {
            return Response::json(['ok'=>false,'message'=>$error->getMessage()],422);
        } catch (RuntimeException $error) {
            return Response::json(['ok'=>false,'message'=>'Location search is temporarily unavailable.'],503);
        }
    }
}
