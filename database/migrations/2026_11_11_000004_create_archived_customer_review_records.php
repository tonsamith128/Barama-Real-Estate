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
        // Archival review records are not active customer inquiries or CRM
        // opportunities. No outbound notifications or public routes use them.
        Schema::create('legacy_customer_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('legacy_import_batches')->restrictOnDelete();
            $table->uuid('source_uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->restrictOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('listings')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('contact_name', 160);
            $table->string('contact_email', 191)->nullable();
            $table->string('contact_phone', 60)->nullable();
            $table->string('preferred_contact', 24)->nullable();
            $table->string('subject', 191)->nullable();
            $table->text('message');
            $table->string('source', 60);
            $table->string('source_status', 32);
            $table->dateTime('responded_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->string('review_status', 24)->default('needs_review')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewer_note', 1000)->nullable();
            $table->timestamps();
            $table->index(['batch_id', 'review_status']);
        });

        Schema::create('legacy_customer_leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('legacy_import_batches')->restrictOnDelete();
            $table->uuid('source_uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('inquiry_id')->nullable()
                ->constrained('legacy_customer_inquiries')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('preferred_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('contact_name', 160);
            $table->string('contact_email', 191)->nullable();
            $table->string('contact_phone', 60)->nullable();
            $table->string('source', 60);
            $table->string('source_stage', 32);
            $table->string('transaction_type', 24)->nullable();
            $table->string('property_type', 50)->nullable();
            $table->decimal('budget_min', 18, 2)->nullable();
            $table->decimal('budget_max', 18, 2)->nullable();
            $table->char('currency', 3);
            $table->text('notes')->nullable();
            $table->dateTime('last_contact_at')->nullable();
            $table->dateTime('next_follow_up_at')->nullable();
            $table->dateTime('won_at')->nullable();
            $table->dateTime('lost_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('review_status', 24)->default('needs_review')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewer_note', 1000)->nullable();
            $table->timestamps();
            $table->index(['company_id','review_status']);
        });
    }

    public function down(): void
    {
        foreach (['legacy_customer_leads','legacy_customer_inquiries'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Cannot discard archived customer lead or inquiry evidence.');
            }
        }
        Schema::dropIfExists('legacy_customer_leads');
        Schema::dropIfExists('legacy_customer_inquiries');
    }
};
