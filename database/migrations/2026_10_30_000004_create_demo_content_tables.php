<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 190);
            $table->string('slug', 190)->unique();
            $table->string('type', 30);
            $table->string('country_code', 2)->nullable();
            $table->foreignId('parent_id')->nullable()->index();
            $table->string('status', 30)->default('active');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::table('companies', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('address_line')->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('website')->nullable();
        });
        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('address_line')->nullable();
            $table->date('launch_date')->nullable();
            $table->date('expected_completion_date')->nullable();
        });
        Schema::table('properties', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('slug', 190)->nullable()->unique();
            $table->unsignedSmallInteger('parking_spaces')->nullable();
            $table->string('furnishing_status', 45)->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();
        });
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width_px')->nullable();
            $table->unsignedInteger('height_px')->nullable();
            $table->string('alt_text')->nullable();
            $table->char('checksum_sha256', 64)->unique();
            $table->timestamps();
        });
        Schema::create('media_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 20);
            $table->unsignedBigInteger('entity_id');
            $table->string('collection', 40)->default('gallery');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['entity_type', 'entity_id', 'position']);
            $table->unique(['media_asset_id', 'entity_type', 'entity_id']);
        });
        Schema::create('legal_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 190)->unique();
            $table->string('title', 190);
            $table->string('summary', 500)->nullable();
            $table->longText('content_html');
            $table->string('status', 25)->default('draft');
            $table->timestamps();
        });
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 190)->unique();
            $table->json('value');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
        Schema::create('demo_imports', function (Blueprint $table): void {
            $table->id();
            $table->char('archive_sha256', 64)->unique();
            $table->string('archive_version', 40);
            $table->foreignId('imported_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('marketplace_records');
            $table->unsignedInteger('image_count');
            $table->timestamp('completed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_imports');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('legal_policies');
        Schema::dropIfExists('media_attachments');
        Schema::dropIfExists('media_assets');
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['slug', 'parking_spaces', 'furnishing_status', 'year_built']);
        });
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['address_line', 'launch_date', 'expected_completion_date']);
        });
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['address_line', 'email', 'phone', 'website']);
        });
        Schema::dropIfExists('locations');
    }
};
