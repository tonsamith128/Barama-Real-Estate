<?php

declare(strict_types=1);

namespace Barama\Services;

use DomainException;
use RuntimeException;

/** Mapbox Geocoding v6; user-triggered requests; temporary display only, never persisted. */
final class MapboxLocationClient
{
    /** @return array<int,array<string,mixed>> */
    public function suggestions(string $token, string $query, string $country = ''): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 3 || mb_strlen($query) > 120 || preg_match('/[\x00-\x1f\x7f]/', $query)) {
            throw new DomainException('Search for an address or place using 3–120 characters.');
        }
        if ($country !== '' && preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            throw new DomainException('Select a valid country.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL is required for Mapbox requests.');
        }
        $queryParams = [
            'q' => $query, 'access_token' => $token, 'limit' => 5,
            'autocomplete' => 'false', 'permanent' => 'false',
        ];
        if ($country !== '') $queryParams['country'] = strtolower($country);
        $url = 'https://api.mapbox.com/search/geocode/v6/forward?'.http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('Unable to initialize Mapbox connection.');
        $options = [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 9,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'BaramaLocationProvider/1.0',
        ];
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'https';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        curl_setopt_array($ch, $options);
        try {
            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response === false) throw new RuntimeException('Mapbox could not be reached securely.');
        } finally { curl_close($ch); }
        if ($status === 401 || $status === 403) throw new DomainException('Mapbox rejected the configured token or its restrictions.');
        if ($status === 429) throw new DomainException('Mapbox rate limit reached. Please wait and retry.');
        if ($status !== 200) throw new RuntimeException('Mapbox address search is temporarily unavailable.');
        if (strlen((string)$response) > 512000) throw new RuntimeException('Unexpectedly large Mapbox response.');
        $json = json_decode((string)$response, true);
        if (!is_array($json) || !is_array($json['features'] ?? null)) {
            throw new RuntimeException('Mapbox returned an unexpected response.');
        }
        $rows=[];
        foreach (array_slice($json['features'], 0, 5) as $feature) {
            if (!is_array($feature)) continue;
            $properties = $feature['properties'] ?? [];
            $context = $properties['context'] ?? [];
            $code = strtoupper((string)($context['country']['country_code'] ?? ''));
            $label = (string)($properties['full_address'] ?? $properties['name_preferred'] ?? $properties['name'] ?? '');
            $coords = $feature['geometry']['coordinates'] ?? [];
            if ($label === '' || !is_array($coords) || count($coords) < 2) continue;
            $lng = (float)$coords[0]; $lat = (float)$coords[1];
            if (!is_finite($lng) || !is_finite($lat) || abs($lng) > 180 || abs($lat) > 90) continue;
            $rows[] = [
                'label'=>mb_substr($label,0,240), 'country_code'=>preg_match('/^[A-Z]{2}$/',$code) ? $code : '',
                'latitude'=>$lat, 'longitude'=>$lng,
                'type'=>(string)($properties['feature_type'] ?? 'place'),
            ];
        }
        return $rows;
    }
}
