<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 190);
            $table->string('slug', 190)->unique();
            $table->string('company_type', 40)->default('developer');
            $table->string('status', 30)->default('draft')->index();
            $table->text('description')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 190);
            $table->string('slug', 190)->unique();
            $table->string('project_type', 40)->default('residential');
            $table->string('status', 30)->default('draft')->index();
            $table->string('completion_status', 40)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('reference_code', 80)->unique();
            $table->string('title', 190);
            $table->string('property_type', 40)->index();
            $table->string('status', 30)->default('draft')->index();
            $table->text('description')->nullable();
            $table->string('address_line', 255)->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->decimal('area_value', 12, 2)->nullable();
            $table->string('area_unit', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status']);
        });

        Schema::create('listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->string('slug', 190)->unique();
            $table->string('transaction_type', 12)->default('sale')->index();
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('price', 16, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('pricing_period', 24)->nullable();
            $table->boolean('negotiable')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at', 'expires_at']);
            $table->index(['transaction_type', 'price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('companies');
    }
};
