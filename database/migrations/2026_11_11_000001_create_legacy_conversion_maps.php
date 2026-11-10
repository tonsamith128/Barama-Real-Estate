<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mapping evidence only. Never alias source numeric IDs to Laravel IDs.
 * Data conversion is limited to disposable replicas pending acceptance.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('legacy_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_uuid')->unique();
            $table->string('source_schema_label', 100);
            $table->string('status', 32)->default('staging');
            $table->unsignedInteger('mapped_records')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('legacy_identity_maps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('legacy_import_batches')->restrictOnDelete();
            $table->string('source_table', 60);
            $table->unsignedBigInteger('source_id');
            $table->uuid('source_uuid')->nullable();
            $table->unsignedBigInteger('target_id');
            $table->string('source_status', 40)->nullable();
            $table->string('reconciliation_state', 32)->default('pending_review');
            $table->timestamp('created_at')->nullable();
            $table->unique(['batch_id','source_table','source_id'],'legacy_identity_source_unique');
            $table->unique(['batch_id','source_table','target_id'],'legacy_identity_target_unique');
            $table->index(['source_table','source_uuid'],'legacy_identity_uuid_idx');
        });
    }

    public function down(): void
    {
        // Explicitly fail rather than discard a real conversion's evidence.
        if (Schema::hasTable('legacy_identity_maps')
            && DB::table('legacy_identity_maps')->exists()) {
            throw new RuntimeException(
                'Cannot delete legacy identity evidence while mappings exist.'
            );
        }
        Schema::dropIfExists('legacy_identity_maps');
        Schema::dropIfExists('legacy_import_batches');
    }
};
