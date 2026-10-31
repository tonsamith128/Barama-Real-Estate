<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('billing_period', 12);
            $table->unsignedInteger('listing_limit')->default(10);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('commercial_plan_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->restrictOnDelete();
            $table->foreignId('commercial_plan_id')->constrained('commercial_plans')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->index(['commercial_plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_plan_inquiries');
        Schema::dropIfExists('commercial_plans');
    }
};
