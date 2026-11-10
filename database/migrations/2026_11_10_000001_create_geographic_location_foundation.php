<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the existing locations table from the original Laravel
     * 2026_10_30_000004 migration. Existing records and foreign keys remain.
     * No source legacy records are imported or modified by this migration.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            // Nullable is intentional: prior demo/QA locations may already exist.
            // A separate, rehearsed adapter must backfill legacy UUIDs.
            $table->uuid('uuid')->nullable()->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->index(['country_code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropIndex(['country_code', 'status']);
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'latitude', 'longitude']);
        });
    }
};
