<?php

declare(strict_types=1);

namespace App\Services\DemoImport;

use App\Models\DemoImport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Laravel adapter for the existing signed Barama demo format.
 *
 * This service is staging-only until production licensing/publisher entitlement
 * is migrated. Never import untrusted or duplicate archives.
 */
final class DemoContentImporter
{
    public function __construct(private readonly DemoArchiveInspector $inspector)
    {
    }

    public function preview(string $archive): array
    {
        $review = $this->inspector->inspect($archive);
        return [
            'sha256' => $review['sha256'],
            'version' => $review['manifest']['version'],
            'counts' => $review['counts'],
            'images' => $review['images'],
            'policies' => $review['policies'],
            'trusted' => false, // The archive alone has NO detached publisher signature.
        ];
    }

    public function import(string $archive, string $signatureB64, User $administrator): DemoImport
    {
        if (!$administrator->exists || !$administrator->isSystemAdministrator()) {
            throw new RuntimeException('Only an authenticated super administrator may import.');
        }
        if (!app()->environment(['local', 'testing', 'staging']) ||
            config('demo.enable_staging_import') !== true) {
            throw new RuntimeException('Demo import is disabled outside explicitly enabled staging.');
        }

        $guardPath = storage_path('app/demo-import.mutex');
        $guard = @fopen($guardPath, 'c');
        if ($guard === false || !flock($guard, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another demo import is running.');
        }

        $newFiles = [];
        try {
            $review = $this->inspector->inspect($archive);
            if (!DemoArchiveInspector::verifiedPublisherSignature(
                $review['sha256'],
                $signatureB64,
                (string) config('demo.publisher_public_key'),
                (string) config('demo.publisher_fingerprint'),
            )) {
                throw new RuntimeException('Publisher detached Ed25519 signature verification failed.');
            }

            $tables = $review['content']['tables'];
            foreach (['amenities', 'cms_pages', 'project_amenities', 'property_amenities'] as $unsupported) {
                if ($tables[$unsupported] !== []) {
                    throw new RuntimeException('This demo includes unsupported tables: '.$unsupported);
                }
            }
            foreach ([
                'companies', 'projects', 'properties', 'listings', 'locations',
                'media_assets', 'media_attachments', 'legal_policies', 'site_settings',
                'demo_imports',
            ] as $target) {
                if (DB::table($target)->exists()) {
                    throw new RuntimeException('Demo import requires an otherwise empty marketplace: '.$target);
                }
            }

            $mediaDir = storage_path('app/private/demo');
            if (is_link(dirname($mediaDir)) || is_link($mediaDir) ||
                (!is_dir($mediaDir) && !mkdir($mediaDir, 0750, true) && !is_dir($mediaDir))) {
                throw new RuntimeException('Private media directory is unavailable.');
            }

            $zip = new ZipArchive();
            if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
                throw new RuntimeException('Cannot reopen signed archive.');
            }
            try {
                $count = array_sum($review['counts']);
                $result = DB::transaction(function () use (
                    $tables, $review, $zip, $administrator, $mediaDir, &$newFiles, $count
                ): DemoImport {
                    // Recheck under transaction as well as the filesystem mutex.
                    if (DemoImport::query()->exists()) {
                        throw new RuntimeException('A demo import already exists.');
                    }

                    $map = [
                        'locations' => ['id','name','slug','type','country_code','parent_id','status','sort_order','created_at','updated_at'],
                        'companies' => ['id','location_id','name','slug','company_type','status','description','published_at','address_line','email','phone','website','created_at','updated_at'],
                        'projects' => ['id','company_id','location_id','name','slug','project_type','status','completion_status','description','address_line','launch_date','expected_completion_date','published_at','created_at','updated_at'],
                        'properties' => ['id','company_id','project_id','location_id','reference_code','title','slug','property_type','status','description','address_line','bedrooms','bathrooms','parking_spaces','area_value','area_unit','furnishing_status','year_built','created_at','updated_at'],
                        'listings' => ['id','property_id','slug','transaction_type','status','price','currency','pricing_period','negotiable','published_at','expires_at','created_at','updated_at'],
                    ];

                    foreach ($map as $target => $allowed) {
                        $batch = [];
                        foreach ($tables[$target] as $source) {
                            $row = array_intersect_key($source, array_flip($allowed));
                            if ($target === 'properties') {
                                foreach (['bedrooms','bathrooms'] as $field) {
                                    if ($row[$field] !== null) {
                                        $row[$field] = (int) round((float) $row[$field]);
                                    }
                                }
                            }
                            $batch[] = $row;
                            if (count($batch) === 150) {
                                DB::table($target)->insert($batch);
                                $batch = [];
                            }
                        }
                        if ($batch !== []) {
                            DB::table($target)->insert($batch);
                        }
                    }

                    foreach ($tables['media_assets'] as $source) {
                        $digest = $source['checksum_sha256'];
                        $ext = $source['extension'];
                        $name = 'assets/'.$digest.'.'.$ext;
                        $bytes = $zip->getFromName($name);
                        if (!is_string($bytes) || hash('sha256', $bytes) !== $digest ||
                            strlen($bytes) !== (int) $source['size_bytes']) {
                            throw new RuntimeException('Media checksum or size mismatch.');
                        }
                        $info = @getimagesizefromstring($bytes);
                        $actualMime = $info['mime'] ?? null;
                        if (!is_array($info) || $actualMime !== $source['mime_type'] ||
                            (int) $info[0] !== (int) $source['width_px'] ||
                            (int) $info[1] !== (int) $source['height_px']) {
                            throw new RuntimeException('Media image dimensions or MIME mismatch.');
                        }
                        $target = $mediaDir.'/'.$digest.'.'.$ext;
                        if (file_exists($target) || is_link($target) ||
                            file_put_contents($target, $bytes, LOCK_EX) !== strlen($bytes)) {
                            throw new RuntimeException('Private demo media write failed.');
                        }
                        $newFiles[] = $target;
                        chmod($target, 0640);
                        DB::table('media_assets')->insert([
                            'id' => $source['id'],
                            'path' => 'private/demo/'.$digest.'.'.$ext,
                            'original_name' => $source['original_name'],
                            'mime_type' => $source['mime_type'],
                            'size_bytes' => $source['size_bytes'],
                            'width_px' => $source['width_px'],
                            'height_px' => $source['height_px'],
                            'alt_text' => $source['alt_text'],
                            'checksum_sha256' => $digest,
                            'created_at' => $source['created_at'] ?? now(),
                            'updated_at' => $source['updated_at'] ?? now(),
                        ]);
                    }

                    foreach ($tables['media_attachments'] as $source) {
                        DB::table('media_attachments')->insert([
                            'id' => $source['id'],
                            'media_asset_id' => $source['media_asset_id'],
                            'entity_type' => $source['entity_type'],
                            'entity_id' => $source['entity_id'],
                            'collection' => $source['collection'] ?? 'gallery',
                            'position' => $source['position'] ?? 0,
                            'is_primary' => (bool) ($source['is_primary'] ?? false),
                            'created_at' => $source['created_at'] ?? now(),
                            'updated_at' => now(),
                        ]);
                    }

                    foreach ($review['content']['policy_center']['policies'] ?? [] as $policy) {
                        DB::table('legal_policies')->insert([
                            'slug' => $policy['slug'],
                            'title' => $policy['title'],
                            'summary' => $policy['summary'] ?? null,
                            'content_html' => $policy['content_html'],
                            'status' => 'draft', // Always require new installation's operator review.
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    foreach ($review['content']['settings'] as $key => $value) {
                        if (!in_array($key, ['cms.site_content', 'cms.footer_content', 'appearance.frontend'], true)) {
                            throw new RuntimeException('Unsupported demo appearance setting.');
                        }
                        DB::table('site_settings')->insert([
                            'key' => $key,
                            'value' => json_encode($value, JSON_THROW_ON_ERROR),
                            'is_active' => false, // Templates never overwrite live appearance automatically.
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $record = DemoImport::query()->create([
                        'archive_sha256' => $review['sha256'],
                        'archive_version' => $review['manifest']['version'],
                        'imported_by' => $administrator->getKey(),
                        'marketplace_records' => $count,
                        'image_count' => $review['images'],
                        'completed_at' => now(),
                    ]);
                    DB::table('audit_events')->insert([
                        'actor_id' => $administrator->getKey(),
                        'action' => 'admin.demo.imported',
                        'subject_type' => 'demo_import',
                        'subject_id' => $record->getKey(),
                        'context' => json_encode([
                            'archive_sha256' => $review['sha256'],
                            'images' => $review['images'],
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    return $record;
                }, 1);
            } finally {
                $zip->close();
            }
            return $result;
        } catch (Throwable $error) {
            foreach ($newFiles as $file) {
                if (is_file($file) && !is_link($file)) {
                    @unlink($file);
                }
            }
            throw $error;
        } finally {
            flock($guard, LOCK_UN);
            fclose($guard);
        }
    }
}
