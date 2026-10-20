<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

/**
 * Presentation-only freshness boundary for the LAST signed ZIP inspection.
 *
 * Browser/session state is not an install, entitlement, or rollback authority.
 * File/key digests must be recomputed independently by the caller at display.
 * A successful inspection is historical until explicitly repeated.
 */
final class OneClickInspectionFreshnessV1
{
    private const MAX_AGE_SECONDS = 600;
    private const SHA256 = '/^[a-f0-9]{64}$/D';
    private const VERSION = '/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D';

    /**
     * @param mixed $record Never trust raw session fields.
     * @return array{status:string,verified:bool,version:string,channel:string,files:int,checked_at:string,archive_sha256:string,detail:string,install_enabled:bool}
     */
    public static function display(
        mixed $record,
        int $now,
        string $installedVersion,
        bool $inspectorReady,
        ?string $currentArchiveSha256,
        ?string $currentTrustedKeysSha256
    ): array {
        $out = [
            'status' => 'Not inspected',
            'verified' => false,
            'version' => '',
            'channel' => '',
            'files' => 0,
            'checked_at' => 'Never',
            'archive_sha256' => '',
            'detail' => 'Inspect a publisher-signed staged release to see its details.',
            'install_enabled' => false,
        ];
        if (!is_array($record)) {
            return $out;
        }

        $status = $record['status'] ?? null;
        if ($status === 'Inspection unavailable or rejected'
            || $status === 'Please wait before checking again') {
            $out['status'] = $status;
            $out['detail'] = $status === 'Please wait before checking again'
                ? 'Reinspection is rate limited; no release has been authorized.'
                : 'The signed package could not be verified. No update was installed.';
            return $out;
        }
        if ($status !== 'Signed package verified (not installed)') {
            return $out;
        }
        $out['status'] = 'Reinspection required';
        $out['detail'] = 'The earlier signed-package result expired or its version, archive, or trusted keys changed.';

        $checked = $record['verified_at'] ?? null;
        $archive = $record['archive_sha256'] ?? null;
        $catalog = $record['trusted_keys_sha256'] ?? null;
        $version = $record['version'] ?? null;
        $channel = $record['channel'] ?? null;
        $files = $record['files'] ?? null;

        if (!$inspectorReady || !is_int($now) || !is_int($checked)
            || $checked < 1 || $checked > $now || $now - $checked > self::MAX_AGE_SECONDS
            || !is_string($installedVersion)
            || preg_match(self::VERSION, $installedVersion) !== 1
            || ($record['site_version'] ?? null) !== $installedVersion
            || !is_string($version) || preg_match(self::VERSION, $version) !== 1
            || version_compare($version, $installedVersion, '<=')
            || !in_array($channel, ['stable', 'beta'], true)
            || !is_int($files) || $files < 2 || $files > 8000
            || !self::sha($archive) || !self::sha($catalog)
            || !self::sha($currentArchiveSha256) || !self::sha($currentTrustedKeysSha256)
            || !hash_equals($archive, $currentArchiveSha256)
            || !hash_equals($catalog, $currentTrustedKeysSha256)) {
            return $out;
        }

        $out['status'] = 'Signed package verified (not installed)';
        $out['verified'] = true;
        $out['version'] = $version;
        $out['channel'] = $channel;
        $out['files'] = $files;
        $out['checked_at'] = gmdate('Y-m-d H:i', $checked) . ' UTC';
        $out['archive_sha256'] = $archive;
        $out['detail'] = 'An earlier signed inspection is still within its freshness window and both local digests match. Reverify independently before any installation.';
        return $out;
    }

    private static function sha(mixed $value): bool
    {
        return is_string($value) && preg_match(self::SHA256, $value) === 1;
    }

    private function __construct()
    {
    }
}
