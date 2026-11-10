<?php

declare(strict_types=1);

namespace Tests\Feature\Release;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class LegacyConversionEvidenceSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_batch_blocks_destructive_schema_rollback(): void
    {
        DB::table('legacy_import_batches')->insert([
            'batch_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'source_schema_label' => 'isolated_legacy_clone',
            'status' => 'staged_needs_review',
            'mapped_records' => 0,
            'started_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path(
            'migrations/2026_11_11_000001_create_legacy_conversion_maps.php'
        );
        try {
            $migration->down();
            $this->fail('Rollback must not drop a recorded import batch.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Cannot discard recorded legacy import batches',
                $e->getMessage());
        }
        $this->assertTrue(Schema::hasTable('legacy_import_batches'));
        $this->assertTrue(Schema::hasTable('legacy_identity_maps'));
        $this->assertDatabaseCount('legacy_import_batches', 1);
    }

    public function test_source_and_destination_identity_is_preserved_with_privilege_review(): void
    {
        $batch = DB::table('legacy_import_batches')->insertGetId([
            'batch_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'source_schema_label' => 'isolated_legacy_clone',
            'status' => 'staged_needs_review',
            'mapped_records' => 1,
            'started_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('legacy_identity_maps')->insert([
            'batch_id' => $batch,
            'source_table' => 'properties',
            'source_id' => 450,
            'source_uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'target_id' => 71,
            'source_status' => 'published',
            'reconciliation_state' => 'pending_review',
            'created_at' => now(),
        ]);

        $map = DB::table('legacy_identity_maps')->where('batch_id', $batch)->first();
        $this->assertSame(450, (int) $map->source_id);
        $this->assertSame(71, (int) $map->target_id);
        $this->assertSame('published', $map->source_status);
        $this->assertSame('pending_review', $map->reconciliation_state);
        $this->assertNotEquals($map->source_id, $map->target_id);
    }
}
