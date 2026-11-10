<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive geographic foundation for clean Laravel installations.
     * This is not an importer for deployed Pure PHP customer databases.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_id')->nullable()
                ->constrained('locations')->nullOnDelete();
            $table->string('type', 32);
            $table->char('country_code', 2)->nullable();
            $table->string('name', 160);
            $table->string('slug', 191)->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 24)->default('active');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['parent_id', 'status', 'sort_order']);
            $table->index(['country_code', 'status']);
        });

        // Each relationship is optional so existing Laravel rows stay intact.
        Schema::table('companies', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()
                ->constrained('locations')->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()
                ->constrained('locations')->nullOnDelete();
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()
                ->constrained('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('properties', fn (Blueprint $table) => $table->dropConstrainedForeignId('location_id'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropConstrainedForeignId('location_id'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropConstrainedForeignId('location_id'));

        Schema::dropIfExists('locations');
    }
};
