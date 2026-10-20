<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

/**
 * Presentation-only progress for the customer-facing One-click Update Center.
 *
 * Accepts only already-sanitized signals from SystemCenterController. These
 * observations are NOT publisher entitlement, update consent, backup evidence,
 * or root-worker authorization. No I/O, secrets, session writes or execution.
 */
final class OneClickDashboardGateV1
{
    /** @param array<string,mixed> $signals
     *  @return array{install_enabled:bool,status:string,steps:list<array{label:string,status:string,detail:string}>,blockers:list<string>}
     */
    public static function assess(array $signals): array
    {
        $versionMatch = ($signals['version_match'] ?? null) === true;
        $licenseRecorded = ($signals['license_recorded'] ?? null) === true;
        $feedConfigured = ($signals['feed_ready'] ?? null) === true;
        $entitled = ($signals['entitlement'] ?? null) === 'Eligible';
        $signedDiscovery = ($signals['feed_state'] ?? null) === 'Signed release verified';
        $newer = ($signals['newer_version'] ?? null) === true;
        $archiveInspected = ($signals['archive_inspected'] ?? null) === true;
        $steps = [
            [
                'label' => 'Installed application',
                'status' => $versionMatch ? 'Version matched' : 'Review required',
                'detail' => $versionMatch
                    ? 'Source and installed version records match.'
                    : 'Resolve version records before preparing an update.',
            ],
            [
                'label' => 'Publisher discovery',
                'status' => $feedConfigured && $entitled && $signedDiscovery && $newer
                    ? 'Newer release reported' : 'Not confirmed',
                'detail' => !$feedConfigured
                    ? 'Trusted publisher feed is not configured.'
                    : (!$licenseRecorded
                        ? 'The local installation license needs review.'
                        : 'Publisher discovery does not download or authorize installation.'),
            ],
            [
                'label' => 'Signed release package',
                'status' => $archiveInspected ? 'Inspection reported' : 'Not verified',
                'detail' => $archiveInspected
                    ? 'A session reported an offline package inspection. The installer must independently reverify the exact signed bytes.'
                    : 'No independently verified package is ready for installation.',
            ],
            [
                'label' => 'Backup and database recovery',
                'status' => 'Not proven',
                'detail' => 'A consistent code and SQL backup, isolated restoration, and crash recovery are required.',
            ],
            [
                'label' => 'Administrator and worker authorization',
                'status' => 'Not implemented',
                'detail' => 'Web-originated requests must never authorize privileged installation.',
            ],
            [
                'label' => 'One-click installation',
                'status' => 'Disabled',
                'detail' => 'No privileged installation worker is connected to this dashboard.',
            ],
        ];

        $blockers = ['PRIVILEGED_INSTALLER_NOT_IMPLEMENTED',
            'INDEPENDENT_OPERATOR_APPROVAL_REQUIRED',
            'COMPLETE_BACKUP_AND_RECOVERY_NOT_PROVEN',
            'INDEPENDENT_PACKAGE_AND_ENTITLEMENT_RECHECK_REQUIRED'];
        if (!$versionMatch) {
            $blockers[] = 'VERSION_RECORDS_MISMATCH';
        }
        if (!$feedConfigured || !$entitled || !$signedDiscovery || !$newer) {
            $blockers[] = 'NEWER_SIGNED_ENTITLED_RELEASE_NOT_CONFIRMED';
        }
        if (!$archiveInspected) {
            $blockers[] = 'SIGNED_ARCHIVE_NOT_INSPECTED';
        }

        return [
            'install_enabled' => false,
            'status' => 'Review only - installation disabled',
            'steps' => $steps,
            'blockers' => $blockers,
        ];
    }

    private function __construct()
    {
    }
}
