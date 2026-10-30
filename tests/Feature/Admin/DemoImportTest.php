<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\DemoImport;
use App\Models\Listing;
use App\Models\User;
use App\Services\DemoImport\DemoArchiveInspector;
use App\Services\DemoImport\DemoContentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

final class DemoImportTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryFiles = [];
    private array $assetFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        foreach (array_merge($this->temporaryFiles, $this->assetFiles) as $path) {
            if (is_file($path) && !is_link($path)) {
                @unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_admin_workspace_rejects_guests_and_normal_users(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertForbidden();
        $this->get('/dashboard/system/demo-importer')->assertForbidden();
    }

    public function test_only_super_administrator_can_view_demo_importer(): void
    {
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Admin Dashboard');
        $this->get('/dashboard/system/demo-importer')
            ->assertOk()
            ->assertSee('Demo Content Importer');
    }

    public function test_original_demo_zip_shape_can_be_previewed_and_imported_when_signed(): void
    {
        [$path, $digest, $keypair, $imagePath] = $this->fixture();
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $public = sodium_crypto_sign_publickey($keypair);
        config([
            'demo.enable_staging_import' => true,
            'demo.publisher_public_key' => base64_encode($public),
            'demo.publisher_fingerprint' => hash('sha256', $public),
        ]);

        $service = app(DemoContentImporter::class);
        $preview = $service->preview($path);
        $this->assertSame('0.9.26', $preview['version']);
        $this->assertSame(1, $preview['counts']['listings']);
        $this->assertSame(1, $preview['images']);
        $this->assertFalse($preview['trusted']);

        $this->expectException(RuntimeException::class);
        $service->import($path, base64_encode(random_bytes(SODIUM_CRYPTO_SIGN_BYTES)), $admin);
    }

    public function test_verified_signed_import_maps_records_and_prevents_duplicates(): void
    {
        [$path, $digest, $keypair, $imagePath] = $this->fixture();
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $public = sodium_crypto_sign_publickey($keypair);
        config([
            'demo.enable_staging_import' => true,
            'demo.publisher_public_key' => base64_encode($public),
            'demo.publisher_fingerprint' => hash('sha256', $public),
        ]);
        $signature = base64_encode(sodium_crypto_sign_detached(hex2bin($digest), sodium_crypto_sign_secretkey($keypair)));
        $service = app(DemoContentImporter::class);

        $import = $service->import($path, $signature, $admin);
        $this->assertSame(1, $import->image_count);
        $this->assertSame(1, DemoImport::query()->count());
        $this->assertSame(1, Company::query()->count());
        $this->assertSame(1, Listing::query()->visibleToPublic()->count());
        $this->assertDatabaseHas('legal_policies', ['slug' => 'terms-of-service', 'status' => 'draft']);
        $this->assertDatabaseHas('site_settings', ['key' => 'cms.site_content', 'is_active' => 0]);
        $this->assertFileExists($imagePath);

        $this->expectException(RuntimeException::class);
        $service->import($path, $signature, $admin);
    }

    private function fixture(): array
    {
        $time = now()->subDay()->format('Y-m-d H:i:s');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=', true);
        $checksum = hash('sha256', $image);
        $assetName = 'assets/'.$checksum.'.png';
        $this->assetFiles[] = storage_path('app/private/demo/'.$checksum.'.png');

        $record = static fn (array $attributes): array => array_merge([
            'id' => 1, 'created_at' => $time, 'updated_at' => $time,
        ], $attributes);
        $tables = [
            'locations' => [$record([
                'parent_id' => null, 'type' => 'city', 'country_code' => 'KH',
                'name' => 'Battambang', 'slug' => 'demo-battambang',
                'status' => 'active', 'sort_order' => 1,
            ])],
            'amenities' => [],
            'companies' => [$record([
                'location_id' => 1, 'name' => 'Demo Company', 'slug' => 'demo-company',
                'company_type' => 'agency', 'status' => 'approved', 'description' => 'Test',
                'published_at' => $time, 'address_line' => 'Battambang',
                'email' => null, 'phone' => null, 'website' => null,
            ])],
            'projects' => [$record([
                'company_id' => 1, 'location_id' => 1, 'name' => 'Demo Project',
                'slug' => 'demo-project', 'project_type' => 'residential',
                'status' => 'published', 'completion_status' => 'under_construction',
                'published_at' => $time, 'description' => 'Test',
                'address_line' => 'Battambang', 'launch_date' => null,
                'expected_completion_date' => null,
            ])],
            'properties' => [$record([
                'company_id' => 1, 'project_id' => 1, 'location_id' => 1,
                'reference_code' => 'DEMO-1', 'title' => 'Demo Villa',
                'slug' => 'demo-villa', 'property_type' => 'villa',
                'status' => 'approved', 'description' => 'Test',
                'address_line' => 'Battambang', 'bedrooms' => '4.0',
                'bathrooms' => '3.0', 'parking_spaces' => 2,
                'area_value' => '100.00', 'area_unit' => 'm2',
                'furnishing_status' => null, 'year_built' => 2023,
            ])],
            'listings' => [$record([
                'property_id' => 1, 'slug' => 'demo-villa',
                'transaction_type' => 'sale', 'status' => 'published',
                'price' => '120000.00', 'currency' => 'USD',
                'pricing_period' => null, 'negotiable' => 0,
                'published_at' => $time, 'expires_at' => null,
            ])],
            'cms_pages' => [],
            'project_amenities' => [],
            'property_amenities' => [],
            'media_assets' => [$record([
                'disk' => 'private', 'path' => 'demo/'.$checksum.'.png',
                'original_name' => 'photo.png', 'mime_type' => 'image/png',
                'extension' => 'png', 'size_bytes' => strlen($image),
                'width_px' => 1, 'height_px' => 1, 'alt_text' => 'Test image',
                'checksum_sha256' => $checksum,
            ])],
            'media_attachments' => [[
                'id' => 1, 'media_asset_id' => 1, 'entity_type' => 'listing',
                'entity_id' => 1, 'collection' => 'gallery',
                'position' => 0, 'is_primary' => 1, 'created_at' => $time,
            ]],
        ];
        $content = [
            'schema' => 'barama-demo-content-v1',
            'components' => [],
            'settings' => ['cms.site_content' => ['homepage' => ['title' => 'Demo']]],
            'tables' => $tables,
            'policy_center' => ['policies' => [[
                'slug' => 'terms-of-service', 'title' => 'Terms of Service',
                'summary' => 'Test policy', 'content_html' => '<p>Draft legal template</p>',
            ]]],
        ];
        $manifest = [
            'product' => 'barama-real-estate', 'demo_slug' => 'live',
            'schema_version' => 1, 'version' => '0.9.26',
        ];

        $path = tempnam(sys_get_temp_dir(), 'barama-demo-');
        $this->temporaryFiles[] = $path;
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->addFromString('content.json', json_encode($content, JSON_THROW_ON_ERROR));
        $zip->addFromString('review.json', '{"status":"fixture"}');
        $zip->addFromString($assetName, $image);
        $zip->close();

        return [$path, hash_file('sha256', $path), sodium_crypto_sign_keypair(), storage_path('app/private/demo/'.$checksum.'.png')];
    }
}
