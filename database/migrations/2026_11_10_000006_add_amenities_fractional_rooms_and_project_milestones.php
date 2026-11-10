<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy Barama stores decimal (5,1) room counts. Alter in place so
        // existing identifiers, queries and numeric filters keep their meaning.
        Schema::table('properties', function (Blueprint $table): void {
            $table->decimal('bedrooms', 5, 1)->nullable()->change();
            $table->decimal('bathrooms', 5, 1)->nullable()->change();
        });

        Schema::create('amenities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('name', 140);
            $table->string('slug', 160)->unique();
            $table->string('category', 80)->default('general');
            $table->string('icon_key', 100)->nullable();
            $table->string('status', 24)->default('active');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index(['category', 'status']);
        });

        Schema::create('project_amenities', function (Blueprint $table): void {
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained('amenities')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['project_id', 'amenity_id']);
        });

        Schema::create('property_amenities', function (Blueprint $table): void {
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained('amenities')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['property_id', 'amenity_id']);
        });

        Schema::create('project_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('title', 190);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('planned');
            $table->date('target_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'position']);
        });
    }

    public function down(): void
    {
        // Never truncate fractional legacy data during rollback.
        $hasFractions = DB::table('properties')
            ->where(function ($query): void {
                $query->whereRaw('bedrooms IS NOT NULL AND bedrooms <> FLOOR(bedrooms)')
                    ->orWhereRaw('bathrooms IS NOT NULL AND bathrooms <> FLOOR(bathrooms)');
            })->exists();
        if ($hasFractions) {
            throw new RuntimeException(
                'Rollback blocked: fractional room values require a lossless conversion plan.'
            );
        }

        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('property_amenities');
        Schema::dropIfExists('project_amenities');
        Schema::dropIfExists('amenities');
        Schema::table('properties', function (Blueprint $table): void {
            $table->unsignedSmallInteger('bedrooms')->nullable()->change();
            $table->unsignedSmallInteger('bathrooms')->nullable()->change();
        });
    }
};
