<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive only: preserve original user/company/project/property/listing IDs.
        Schema::table('projects', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('completed_at')->nullable();
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->smallInteger('floor_number')->nullable();
            $table->unsignedSmallInteger('total_floors')->nullable();
            $table->decimal('land_area_value', 14, 2)->nullable();
            $table->string('land_area_unit', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });

        Schema::table('listings', function (Blueprint $table): void {
            $table->decimal('deposit_amount', 16, 2)->nullable();
            $table->date('available_from')->nullable();
        });

        Schema::create('catalog_revisions', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 24);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedInteger('revision_number');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['subject_type', 'subject_id', 'revision_number'], 'catalog_revision_subject_version_unique');
            $table->index(['subject_type', 'subject_id', 'created_at'], 'catalog_revision_subject_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_revisions');
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropColumn(['deposit_amount', 'available_from']);
        });
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn([
                'floor_number', 'total_floors', 'land_area_value',
                'land_area_unit', 'latitude', 'longitude',
            ]);
        });
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude', 'completed_at']);
        });
    }
};
