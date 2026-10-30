<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->restrictOnDelete();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('full_name', 120);
            $table->string('email', 190);
            $table->string('phone', 50)->nullable();
            $table->text('message');
            $table->string('status', 25)->default('new');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['listing_id', 'status', 'created_at']);
        });
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 90);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('inquiries');
    }
};
