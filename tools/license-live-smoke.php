<?php

declare(strict_types=1);

use Barama\Services\LicenseManagerClient;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/runtime.php';

$required = [
    'BARAMA_LIVE_DIRECT_LICENSE_KEY',
    'BARAMA_LIVE_DIRECT_DOMAIN',
    'BARAMA_LIVE_ENVATO_PURCHASE_CODE',
    'BARAMA_LIVE_ENVATO_DOMAIN',
];

$values = [];

foreach ($required as $name) {
    $value = trim((string) getenv($name));

    if ($value === '') {
        throw new RuntimeException(
            'Required live-license secret is unavailable: ' . $name
        );
    }

    $values[$name] = $value;
}

$client = new LicenseManagerClient();

$expectRejected = static function (callable $action, string $label): void {
    $rejected = false;

    try {
        $action();
    } catch (RuntimeException $exception) {
        if (LicenseManagerClient::isTemporaryFailure($exception)) {
            throw new RuntimeException(
                'Live License Manager was temporarily unavailable while verifying: '
                . $label,
                0,
                $exception
            );
        }

        $rejected = true;
    }

    if (!$rejected) {
        throw new RuntimeException(
            'Live License Manager did not reject: ' . $label
        );
    }
};

$exercise = static function (
    string $method,
    string $credential,
    string $domain
) use ($client, $expectRejected): void {
    $domain = LicenseManagerClient::canonicalDomain($domain);

    if ($domain === '') {
        throw new RuntimeException(
            'Live test domain is invalid for ' . $method . '.'
        );
    }

    $installationId = bin2hex(random_bytes(16));
    $activationToken = null;

    try {
        $activation = $client->activate(
            $credential,
            $domain,
            $installationId,
            $method
        );

        $activationToken = trim(
            (string) ($activation['activation_token'] ?? '')
        );

        if (
            $activationToken === ''
            || ($activation['product_slug'] ?? '') !== 'barama-real-estate'
            || ($activation['domain'] ?? '') !== $domain
            || ($activation['installation_id'] ?? '') !== $installationId
            || ($activation['verification_method'] ?? '') !== $method
        ) {
            throw new RuntimeException(
                'Live activation returned an invalid Barama contract.'
            );
        }

        $validated = $client->validateActive(
            $activationToken,
            $domain,
            $installationId
        );

        if (
            ($validated['product_slug'] ?? '') !== 'barama-real-estate'
            || ($validated['status'] ?? '') !== 'active'
        ) {
            throw new RuntimeException(
                'Live active-license validation returned invalid state.'
            );
        }

        $expectRejected(
            static fn () => $client->validateActive(
                $activationToken,
                'mismatch-' . substr(hash('sha256', $domain), 0, 10)
                    . '.example.test',
                $installationId
            ),
            $method . ' wrong-domain validation'
        );

        $expectRejected(
            static fn () => $client->validateActive(
                $activationToken,
                $domain,
                bin2hex(random_bytes(16))
            ),
            $method . ' wrong-installation validation'
        );
    } finally {
        if (is_string($activationToken) && $activationToken !== '') {
            $client->deactivate(
                $activationToken,
                $domain,
                $installationId
            );

            $expectRejected(
                static fn () => $client->validateActive(
                    $activationToken,
                    $domain,
                    $installationId
                ),
                $method . ' deactivated-token validation'
            );
        }
    }

    fwrite(
        STDOUT,
        '[PASS] Live License Manager '
        . $method
        . ': activation, validation, explicit binding rejection and deactivation verified.'
        . PHP_EOL
    );
};

$exercise(
    'direct',
    $values['BARAMA_LIVE_DIRECT_LICENSE_KEY'],
    $values['BARAMA_LIVE_DIRECT_DOMAIN']
);

$exercise(
    'envato',
    $values['BARAMA_LIVE_ENVATO_PURCHASE_CODE'],
    $values['BARAMA_LIVE_ENVATO_DOMAIN']
);

foreach ([
    'direct' => $values['BARAMA_LIVE_DIRECT_DOMAIN'],
    'envato' => $values['BARAMA_LIVE_ENVATO_DOMAIN'],
] as $method => $domain) {
    $expectRejected(
        static fn () => $client->activate(
            'BARAMA-LIVE-INVALID-' . strtoupper(bin2hex(random_bytes(8))),
            $domain,
            bin2hex(random_bytes(16)),
            $method
        ),
        $method . ' invalid credential'
    );
}

fwrite(
    STDOUT,
    '[PASS] Live License Manager invalid credentials were rejected for both verification methods.'
    . PHP_EOL
);
