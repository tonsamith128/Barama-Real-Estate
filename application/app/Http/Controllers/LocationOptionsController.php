<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Repositories\GlobalLocationHierarchyRepository;
use Barama\Repositories\GlobalLocationRepository;

final class LocationOptionsController
{
    /* BARAMA_GLOBAL_LOCATION_OPTIONS_V110 */

    public function index(Request $request): Response
    {
        $locations = new GlobalLocationHierarchyRepository();
        $selected = (int) $request->input('location_id', 0);

        if ($selected > 0) {
            $selection = $locations->selection($selected);
            $countries = $locations->selectableCountries();

            if (is_array($selection)) {
                $selectedCountry = strtoupper((string) ($selection['country_code'] ?? ''));
                $hasSelectedCountry = false;
                foreach ($countries as $country) {
                    if (strtoupper((string) ($country['country_code'] ?? '')) === $selectedCountry) {
                        $hasSelectedCountry = true;
                        break;
                    }
                }

                if (!$hasSelectedCountry && $selectedCountry !== '') {
                    $savedCountry = (new GlobalLocationRepository())->country($selectedCountry);
                    if (is_array($savedCountry)) {
                        array_unshift($countries, $savedCountry);
                    }
                }
            }

            return Response::json([
                'ok' => true,
                'selection' => $selection,
                'countries' => $countries,
            ]);
        }

        $countryCode = strtoupper(trim((string) $request->input('country', '')));
        if ($countryCode === '') {
            return Response::json([
                'ok' => true,
                'countries' => $locations->selectableCountries(),
            ]);
        }

        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            return Response::json(['ok' => false, 'message' => 'Invalid country code.'], 422);
        }

        $parentRaw = trim((string) $request->input('parent_id', ''));
        $parentId = $parentRaw !== '' ? (int) $parentRaw : null;
        if ($parentId !== null && $parentId < 1) {
            return Response::json(['ok' => false, 'message' => 'Invalid parent location.'], 422);
        }

        return Response::json([
            'ok' => true,
            'country_code' => $countryCode,
            'parent_id' => $parentId,
            'locations' => $locations->children($countryCode, $parentId),
        ]);
    }
}
