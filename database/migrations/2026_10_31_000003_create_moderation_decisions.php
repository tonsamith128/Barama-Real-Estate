<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 24);
            $table->unsignedBigInteger('subject_id');
            $table->string('decision', 24);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'created_at'], 'moderation_subject_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_decisions');
    }
};
