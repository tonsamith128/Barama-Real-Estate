<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\CatalogLocationEditService;
use DomainException;
use InvalidArgumentException;

final class CatalogLocationEditController
{
    public function save(Request $request, string $id): Response
    {
        if (!ctype_digit($id) || (int)$id < 1) {
            return Response::json(['ok'=>false, 'message'=>'Invalid location ID.'], 422);
        }
        try {
            $result = (new CatalogLocationEditService())->update((int)$id, $request->all());
            return Response::json(['ok'=>true, 'data'=>$result]);
        } catch (InvalidArgumentException $e) {
            return Response::json(['ok'=>false, 'message'=>$e->getMessage()], 422);
        } catch (DomainException $e) {
            return Response::json(['ok'=>false, 'message'=>$e->getMessage()], 409);
        }
    }
}
