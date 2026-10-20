<?php

declare(strict_types=1);

/**
 * Staging-only root CLI: authenticated publisher artifact delivery for the
 * independently trusted One-click Updater worker.
 *
 * This script never publishes a release, installs files, runs migrations,
 * opens web routes, or returns activation credentials.
 */
if (PHP_SAPI !== 'cli'
    || !function_exists('posix_geteuid')
    || posix_geteuid() !== 0
    || count($argv) !== 2
    || ($argv[1] ?? '') !== '--download-authenticated') {
    fwrite(STDERR, "ONECLICK_DELIVERY_ROOT_CLI_REQUIRED\n");
    exit(70);
}

$site = '/www/wwwroot/baramastaging.tonsamith.com';
if (is_link($site) || !is_dir($site) || realpath($site) !== $site) {
    fwrite(STDERR, "ONECLICK_DELIVERY_SITE_UNTRUSTED\n");
    exit(70);
}

try {
    require_once $site . '/application/bootstrap/runtime.php';

    $result = \Barama\Services\Updates\PublisherPrivateDeliveryV1::download(
        $site,
        'DOWNLOAD_AUTHENTIC_PUBLISHER_RELEASE_TO_ROOT_ONLY'
    );
    if (($result['status'] ?? null) !== 'AUTHENTICATED_ROOT_PRIVATE_ARTIFACT_RECEIVED') {
        throw new RuntimeException('ONECLICK_DELIVERY_NO_ELIGIBLE_PUBLISHED_RELEASE');
    }

    $archive = $result['archive'] ?? null;
    $envelope = $result['signed_envelope'] ?? null;
    $digest = $result['sha256'] ?? null;
    $version = $result['version'] ?? null;
    $private = '/root/Barama-Publisher-Private-Releases/';

    if (!is_string($archive)
        || !str_starts_with($archive, $private)
        || is_link($archive)
        || !is_file($archive)
        || realpath($archive) !== $archive
        || !is_array($envelope)
        || !is_string($digest)
        || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1
        || !is_string($version)
        || !hash_equals($digest, (string) hash_file('sha256', $archive))
        || ($envelope['manifest']['package_sha256'] ?? null) !== $digest
        || ($envelope['manifest']['version'] ?? null) !== $version) {
        throw new RuntimeException('ONECLICK_DELIVERY_RECEIPT_REJECTED');
    }

    // Root-private output is consumed only by the isolated trusted worker.
    // Envelope contains publisher-signed metadata but no license secrets.
    echo json_encode([
        'schema' => 'barama-oneclick-private-delivery-v1',
        'archive' => $archive,
        'archive_sha256' => $digest,
        'version' => $version,
        'envelope' => $envelope,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
} catch (Throwable) {
    fwrite(STDERR, "ONECLICK_AUTHENTICATED_DELIVERY_BLOCKED\n");
    exit(71);
}
