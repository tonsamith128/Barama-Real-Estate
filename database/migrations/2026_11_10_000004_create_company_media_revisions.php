<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_media_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->restrictOnDelete();
            $table->foreignId('previous_attachment_id')->nullable()->constrained('media_attachments')->nullOnDelete();
            $table->foreignId('approved_attachment_id')->nullable()->constrained('media_attachments')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('collection', 20);
            $table->string('status', 20)->default('pending')->index();
            $table->text('reason');
            $table->text('review_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'collection', 'status'], 'company_media_review_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_media_revisions');
    }
};
