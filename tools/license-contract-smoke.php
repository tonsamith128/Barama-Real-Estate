<?php

declare(strict_types=1);

use Barama\Services\LicenseManagerClient;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/runtime.php';

$installationId = str_repeat('a', 32);
$domain = 'barama-license.example.test';
$events = [];

$client = new LicenseManagerClient(
    static function (string $path, array $payload) use (&$events): array {
        $events[] = [
            'path' => $path,
            'payload' => $payload,
        ];

        if ($path === '/license/v1/activate') {
            return [
                'success' => true,
                'activation_token' => 'contract-token',
                'license' => [
                    'id' => 'contract-license',
                    'product_slug' => 'barama-real-estate',
                    'status' => 'active',
                    'domain' => $payload['domain'],
                    'installation_id' => $payload['installation_id'],
                    'source' => $payload['verification_method'],
                    'is_test' => true,
                ],
            ];
        }

        if ($path === '/license/v1/validate') {
            return [
                'success' => true,
                'license' => [
                    'product_slug' => 'barama-real-estate',
                    'status' => 'active',
                    'domain' => $payload['domain'],
                    'installation_id' => $payload['installation_id'],
                ],
            ];
        }

        if ($path === '/license/v1/deactivate') {
            return ['success' => true];
        }

        throw new RuntimeException('Unexpected contract path.');
    }
);

foreach (['direct', 'envato'] as $method) {
    $activation = $client->activate(
        'BARAMA-CONTRACT-TEST-123456',
        'https://www.' . $domain . ':443/path',
        $installationId,
        $method
    );

    if (
        ($activation['activation_token'] ?? '') !== 'contract-token'
        || ($activation['verification_method'] ?? '') !== $method
        || ($activation['domain'] ?? '') !== $domain
    ) {
        throw new RuntimeException(
            'License activation normalization failed for ' . $method . '.'
        );
    }

    $validated = $client->validateActive(
        (string) $activation['activation_token'],
        $domain,
        $installationId
    );

    if (
        ($validated['status'] ?? '') !== 'active'
        || ($validated['product_slug'] ?? '') !== 'barama-real-estate'
    ) {
        throw new RuntimeException(
            'Active license validation failed for ' . $method . '.'
        );
    }

    $deactivated = $client->deactivate(
        (string) $activation['activation_token'],
        $domain,
        $installationId
    );

    if (($deactivated['success'] ?? false) !== true) {
        throw new RuntimeException(
            'License deactivation failed for ' . $method . '.'
        );
    }
}

$autoEnvato = $client->activate(
    '12345678-1234-1234-1234-123456789abc',
    $domain,
    $installationId,
    'auto'
);
$autoDirect = $client->activate(
    'BARAMA-AUTHOR-KEY-123456',
    $domain,
    $installationId,
    'auto'
);

if (
    ($autoEnvato['verification_method'] ?? '') !== 'envato'
    || ($autoDirect['verification_method'] ?? '') !== 'direct'
) {
    throw new RuntimeException(
        'Automatic purchase verification routing is incorrect.'
    );
}

$invalidMethodBlocked = false;
$nonActiveStatusesBlocked = true;
$missingBindingFieldsBlocked = true;
$wrongDomainBlocked = false;
$wrongProductBlocked = false;
$wrongInstallationBlocked = false;

$temporaryStatuses = [0, 408, 425, 429, 500, 502, 503, 504];
$permanentStatuses = [400, 401, 403, 404, 409, 410, 422];

foreach ($temporaryStatuses as $status) {
    if (!LicenseManagerClient::isTemporaryHttpStatus($status)) {
        throw new RuntimeException(
            'Temporary License Manager HTTP status was not classified as transient: '
            . $status
        );
    }
}

foreach ($permanentStatuses as $status) {
    if (LicenseManagerClient::isTemporaryHttpStatus($status)) {
        throw new RuntimeException(
            'Permanent License Manager HTTP rejection was incorrectly classified as transient: '
            . $status
        );
    }
}

$temporaryException = new RuntimeException(
    'temporary',
    LicenseManagerClient::ERROR_TEMPORARY_UNAVAILABLE
);
$permanentException = new RuntimeException('permanent');

if (
    !LicenseManagerClient::isTemporaryFailure($temporaryException)
    || LicenseManagerClient::isTemporaryFailure($permanentException)
) {
    throw new RuntimeException(
        'License Manager transient exception classification is incorrect.'
    );
}

foreach ([
    'revoked',
    'suspended',
    'invalid',
    'deactivated',
    'expired',
    'inactive',
    'pending',
    'disabled',
] as $status) {
    $statusClient = new LicenseManagerClient(
        static fn (string $path, array $payload): array => [
            'success' => true,
            'license' => [
                'status' => $status,
                'product_slug' => 'barama-real-estate',
                'domain' => $domain,
                'installation_id' => $installationId,
            ],
        ]
    );

    try {
        $statusClient->validateActive(
            'contract-token',
            $domain,
            $installationId
        );
        $nonActiveStatusesBlocked = false;
    } catch (RuntimeException) {
        // Expected: only an explicitly active license may validate.
    }
}

$completeBinding = [
    'status' => 'active',
    'product_slug' => 'barama-real-estate',
    'domain' => $domain,
    'installation_id' => $installationId,
];

foreach (array_keys($completeBinding) as $missingField) {
    $incomplete = $completeBinding;
    unset($incomplete[$missingField]);

    $incompleteClient = new LicenseManagerClient(
        static fn (string $path, array $payload): array => [
            'success' => true,
            'license' => $incomplete,
        ]
    );

    try {
        $incompleteClient->validateActive(
            'contract-token',
            $domain,
            $installationId
        );
        $missingBindingFieldsBlocked = false;
    } catch (RuntimeException) {
        // Expected: every binding field must be returned explicitly.
    }
}

try {
    $client->activate(
        'BARAMA-CONTRACT-TEST-123456',
        $domain,
        $installationId,
        'unsupported'
    );
} catch (RuntimeException) {
    $invalidMethodBlocked = true;
}

foreach ([
    'domain' => [
        'status' => 'active',
        'product_slug' => 'barama-real-estate',
        'domain' => 'other.example.test',
        'installation_id' => $installationId,
    ],
    'product' => [
        'status' => 'active',
        'product_slug' => 'other-product',
        'domain' => $domain,
        'installation_id' => $installationId,
    ],
    'installation' => [
        'status' => 'active',
        'product_slug' => 'barama-real-estate',
        'domain' => $domain,
        'installation_id' => str_repeat('b', 32),
    ],
] as $case => $license) {
    $caseClient = new LicenseManagerClient(
        static fn (string $path, array $payload): array => [
            'success' => true,
            'license' => $license,
        ]
    );

    try {
        $caseClient->validateActive(
            'contract-token',
            $domain,
            $installationId
        );
    } catch (RuntimeException) {
        match ($case) {
            'domain' => $wrongDomainBlocked = true,
            'product' => $wrongProductBlocked = true,
            'installation' => $wrongInstallationBlocked = true,
        };
    }
}

if (
    !$invalidMethodBlocked
    || !$nonActiveStatusesBlocked
    || !$missingBindingFieldsBlocked
    || !$wrongDomainBlocked
    || !$wrongProductBlocked
    || !$wrongInstallationBlocked
) {
    throw new RuntimeException(
        'License Manager contract rejection rules are incomplete.'
    );
}

$activateEvents = array_values(array_filter(
    $events,
    static fn (array $event): bool =>
        $event['path'] === '/license/v1/activate'
));

if (
    count($activateEvents) !== 4
    || ($activateEvents[0]['payload']['verification_method'] ?? '') !== 'direct'
    || ($activateEvents[1]['payload']['verification_method'] ?? '') !== 'envato'
    || ($activateEvents[2]['payload']['verification_method'] ?? '') !== 'envato'
    || ($activateEvents[3]['payload']['verification_method'] ?? '') !== 'direct'
    || ($activateEvents[0]['payload']['product_slug'] ?? '') !== 'barama-real-estate'
) {
    throw new RuntimeException(
        'Direct/Envato activation payload contract is incorrect.'
    );
}

fwrite(
    STDOUT,
    '[PASS] License Manager contract: automatic/direct/envato activation, active validation, '
    . 'explicit domain/product/installation/status binding, all non-active status rejection, '
    . 'incomplete-response rejection, deactivation and '
    . 'temporary-vs-permanent outage classification verified.'
    . PHP_EOL
);
