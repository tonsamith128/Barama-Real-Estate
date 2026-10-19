<?php
declare(strict_types=1);

namespace Barama\Services\Updates {
    final class TrustedReleaseFeed {
        public static function verifyEnvelope(array $envelope, string $current, string $channel): string {
            if ($current !== '0.9.22-dev' || $channel !== 'stable'
                || ($envelope['manifest']['version'] ?? null) !== '0.9.23') {
                throw new \RuntimeException('UNTRUSTED_FIXTURE');
            }
            return '0.9.23';
        }
    }
}

namespace {
    use Barama\Services\Updates\PrivateArtifactReceiverV2;

    // CI-only root-private isolated fixture. Never touches website/config/DB.
    if (getenv('GITHUB_ACTIONS') !== 'true'
        || getenv('GITHUB_REPOSITORY') !== 'tonsamith128/Barama-Real-Estate'
        || !function_exists('posix_geteuid') || posix_geteuid() !== 0) {
        throw new \RuntimeException('ROOT_CI_FIXTURE_ONLY');
    }
    require dirname(__DIR__) . '/application/app/Services/Updates/PrivateArtifactReceiverV2.php';
    $vault = '/root/barama-private-receiver-fixture-' . bin2hex(random_bytes(8));
    if (!mkdir($vault,0700)) throw new \RuntimeException('FIXTURE_VAULT_FAILED');
    $body = "signed-disposable-archive-fixture-bytes\n";
    $sha = hash('sha256', $body);
    $envelope = ['manifest'=>[
        'version'=>'0.9.23',
        'package_bytes'=>strlen($body),
        'package_sha256'=>$sha,
        'release_id'=>'fixture-release-0-9-23'
    ]];
    $archive = $vault . '/fixture-release-0-9-23-' . substr($sha,0,16) . '.zip';
    $receipt = $archive . '.receipt.json';
    $recv = static function (callable $transfer) use ($envelope,$vault): array {
        return PrivateArtifactReceiverV2::receive($envelope,'0.9.22-dev','stable',$vault,$transfer);
    };
    $reject = static function(callable $fn,string $expected): void {
        try { $fn(); }
        catch (\RuntimeException $e) {
            if ($e->getMessage() === $expected) return;
            throw new \RuntimeException('UNEXPECTED_ERROR_'.$e->getMessage());
        }
        throw new \RuntimeException('FAIL_OPEN_'.$expected);
    };
    try {
        $ok = $recv(static function (callable $sink) use ($body): void {
            $sink($body);
        });
        if ($ok['archive'] !== $archive || $ok['receipt'] !== $receipt
            || $ok['sha256'] !== $sha || file_get_contents($archive) !== $body
            || !is_file($receipt)) {
            throw new \RuntimeException('ORDINARY_RECEIPT_FAILED');
        }
        echo "NORMAL_PRIVATE_RECEIVE=PASS\n";
        unlink($archive); unlink($receipt);

        // An archive created by another writer DURING streaming belongs to it.
        $rivalArchive = "other-transfer-owned-archive\n";
        $reject(static function() use ($recv,$body,$archive,$rivalArchive): void {
            $recv(static function(callable $sink) use ($body,$archive,$rivalArchive):void {
                file_put_contents($archive,$rivalArchive);
                $sink($body);
            });
        },'TRANSFER_STAGE_CONFLICT');
        if (file_get_contents($archive) !== $rivalArchive || file_exists($receipt)) {
            throw new \RuntimeException('RACING_ARCHIVE_DELETED');
        }
        echo "RACING_ARCHIVE_PRESERVED=PASS\n";
        unlink($archive);

        // An independently created receipt appears before the no-clobber
        // receipt open. Only the archive *created by this invocation* may be
        // removed on failure; the rival receipt must remain unmodified.
        $rivalReceipt = "other-transfer-owned-receipt\n";
        $reject(static function() use ($recv,$body,$receipt,$rivalReceipt): void {
            $recv(static function(callable $sink) use ($body,$receipt,$rivalReceipt):void {
                file_put_contents($receipt,$rivalReceipt);
                $sink($body);
            });
        },'TRANSFER_RECEIPT_CONFLICT');
        if (file_get_contents($receipt) !== $rivalReceipt || file_exists($archive)) {
            throw new \RuntimeException('RACING_RECEIPT_DELETED_OR_ORPHAN_ARCHIVE');
        }
        echo "RACING_RECEIPT_PRESERVED=PASS\n";
        unlink($receipt);

        $reject(static function() use ($recv,$body):void {
            $recv(static function(callable $sink) use ($body):void {
                $sink($body);
                throw new \RuntimeException('TRANSFER_ABORTED_FIXTURE');
            });
        },'TRANSFER_ABORTED_FIXTURE');
        if (file_exists($archive) || file_exists($receipt)) {
            throw new \RuntimeException('ABORT_LEFT_FINAL_ARTIFACTS');
        }
        $leftovers = array_values(array_diff(scandir($vault) ?: [], ['.','..']));
        if ($leftovers !== []) throw new \RuntimeException('ABORT_LEFT_PARTIAL_ARTIFACTS');
        echo "FAILED_TRANSFER_CLEANUP_OWN_FILES_ONLY=PASS\n";
        echo "REAL_PRIVATE_KEYS_USED=NO\n";
        echo "WEBSITE_WRITES=NO\nDATABASE_CONNECTIONS=NO\nAUTO_INSTALL=DISABLED\n";
    } finally {
        foreach (scandir($vault) ?: [] as $name) {
            if ($name !== '.' && $name !== '..') @unlink($vault . '/' . $name);
        }
        rmdir($vault);
    }
}
