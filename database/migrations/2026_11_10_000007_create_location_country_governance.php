<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Matches archived 0028/0029 data shape without importing or
        // inventing country records, registry keys or third-party datasets.
        Schema::create('location_countries', function (Blueprint $table): void {
            $table->char('country_code', 2)->primary();
            $table->char('iso3', 3)->unique();
            $table->string('name', 160);
            $table->string('native_name', 160)->nullable();
            $table->boolean('enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('data_status', 24)->default('catalog_only');
            $table->timestamps();
            $table->index(['enabled', 'is_default', 'sort_order', 'name'], 'location_country_visible_idx');
        });

        Schema::create('location_data_packs', function (Blueprint $table): void {
            $table->char('country_code', 2)->primary();
            $table->foreign('country_code')->references('country_code')
                ->on('location_countries')->restrictOnDelete();
            $table->string('source', 80);
            $table->string('source_version', 40);
            $table->string('status', 24)->default('installed');
            $table->unsignedInteger('record_count')->default(0);
            $table->char('checksum_sha256', 64);
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['status', 'record_count']);
        });

        Schema::create('location_hierarchy_registry', function (Blueprint $table): void {
            // Legacy external codes may be globally keyed; never assume
            // that the code is a Laravel primary ID or a location UUID.
            $table->string('external_code', 40)->primary();
            $table->char('country_code', 2);
            $table->foreign('country_code')->references('country_code')
                ->on('location_countries')->restrictOnDelete();
            $table->string('parent_external_code', 40)->nullable();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->string('source', 80);
            $table->string('source_type', 80)->nullable();
            $table->boolean('created_location')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->index(['source', 'location_id']);
            $table->index(['country_code', 'parent_external_code'], 'location_registry_country_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_hierarchy_registry');
        Schema::dropIfExists('location_data_packs');
        Schema::dropIfExists('location_countries');
    }
};
