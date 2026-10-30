<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/Installer/Setup.php';
require_once dirname(__DIR__, 2).'/app/Services/Licensing/PublisherLicenseClient.php';

use App\Services\Licensing\PublisherLicenseClient as License;
use Barama\Installer\Setup;

function verifyCheck(bool $ok, string $error): void
{
    if (!$ok) {
        throw new RuntimeException($error);
    }
}

$ci = [
    'SERVER_NAME' => '127.0.0.1',
    'HTTP_HOST' => '127.0.0.1:8765',
    'SERVER_PORT' => '8765',
    'HTTPS' => 'off',
];
verifyCheck(Setup::detectedApplicationUrl($ci) === 'http://127.0.0.1:8765', 'Failed automatic local URL');
verifyCheck(Setup::detectedApplicationUrl([
    'SERVER_NAME' => 'codecanyon.salabandet.com',
    'HTTP_HOST' => 'codecanyon.salabandet.com',
    'SERVER_PORT' => '443',
    'HTTPS' => 'on',
]) === 'https://codecanyon.salabandet.com', 'Failed automatic HTTPS URL');
try {
    Setup::detectedApplicationUrl([
        'SERVER_NAME' => 'codecanyon.salabandet.com',
        'HTTP_HOST' => 'attacker.example',
        'SERVER_PORT' => '443',
        'HTTPS' => 'on',
    ]);
    throw new RuntimeException('Untrusted browser Host header was accepted');
} catch (RuntimeException $e) {
    verifyCheck(str_contains($e->getMessage(), 'does not match'), 'Host-header check did not reject mismatch');
}

$id = bin2hex(random_bytes(16));
$code = '12345678-1234-abcd-abcd-123456789abc';
$domain = 'codecanyon.salabandet.com';
$client = function (string $path, array $body) use ($code, $domain, $id): array {
    verifyCheck($path === '/license/v1/activate', 'Wrong publisher route');
    verifyCheck(($body['purchase_code'] ?? '') === $code, 'Wrong purchase code payload');
    verifyCheck(($body['verification_method'] ?? '') === 'envato', 'Wrong Envato verification method');
    verifyCheck(($body['product_slug'] ?? '') === 'barama-real-estate', 'Wrong product');
    return [
        'success' => true,
        'activation_token' => str_repeat('a', 48),
        'license' => [
            'status' => 'active',
            'product_slug' => 'barama-real-estate',
            'domain' => $domain,
            'installation_id' => $id,
            'source' => 'envato',
        ],
    ];
};
$receipt = License::activate($code, $domain, $id, $client);
verifyCheck(($receipt['status'] ?? null) === 'active', 'Valid license rejected');
verifyCheck(!str_contains(json_encode($receipt), $code), 'Purchase code leaked into receipt');
try {
    License::activate($code, $domain, $id, function () use ($id): array {
        return [
            'success' => true,
            'activation_token' => str_repeat('a', 48),
            'license' => ['status' => 'active','product_slug' => 'another-product',
                'domain' => 'codecanyon.salabandet.com','installation_id' => $id],
        ];
    });
    throw new RuntimeException('Wrong product was accepted');
} catch (RuntimeException $e) {
    verifyCheck(str_contains($e->getMessage(), 'does not match'), 'Product mismatch did not fail safely');
}
try {
    License::activate($code, $domain, $id, fn (): array => ['success' => false]);
    throw new RuntimeException('Publisher rejected code was accepted');
} catch (RuntimeException $e) {
    verifyCheck(str_contains($e->getMessage(), 'could not be verified'), 'Publisher rejection was not enforced');
}
echo "INSTALLER_PURCHASE_CODE_URL_DETECTION_PASS\n";
