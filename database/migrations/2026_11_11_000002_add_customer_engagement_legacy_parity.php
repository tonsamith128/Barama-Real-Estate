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
        Schema::create('comparison_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'listing_id']);
            $table->index(['user_id', 'position']);
        });

        Schema::create('recent_listing_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->timestamp('first_viewed_at');
            $table->timestamp('last_viewed_at');
            $table->unsignedInteger('view_count')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'listing_id']);
            $table->index(['user_id', 'last_viewed_at']);
        });

        Schema::table('saved_searches', function (Blueprint $table): void {
            $table->string('name', 120)->change();
            $table->uuid('legacy_uuid')->nullable()->unique();
            // Preserve original filter/alert decisions without enabling mail,
            // notifications or unsupported geographic filters.
            $table->json('legacy_filter_evidence')->nullable();
            $table->boolean('legacy_review_required')->default(false);
            $table->string('legacy_alert_frequency', 24)->nullable();
            $table->boolean('legacy_alerts_enabled')->default(false);
            $table->timestamp('legacy_last_checked_at')->nullable();
        });

        Schema::table('viewing_requests', function (Blueprint $table): void {
            $table->uuid('legacy_uuid')->nullable()->unique();
            $table->foreignId('legacy_company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->date('legacy_preferred_date')->nullable();
            $table->string('legacy_preferred_window', 40)->nullable();
            $table->date('legacy_alternate_date')->nullable();
            $table->string('legacy_alternate_window', 40)->nullable();
            $table->timestamp('legacy_scheduled_for')->nullable();
            $table->string('legacy_original_status', 32)->nullable();
            $table->string('legacy_company_note', 1000)->nullable();
            $table->timestamp('legacy_cancelled_at')->nullable();
            $table->timestamp('legacy_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['comparison_items', 'recent_listing_views'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Cannot remove historical customer engagement records.');
            }
        }
        if (Schema::hasColumn('saved_searches', 'legacy_uuid')
            && DB::table('saved_searches')->whereNotNull('legacy_uuid')->exists()) {
            throw new RuntimeException('Cannot discard unreconciled historical saved-search evidence.');
        }
        if (Schema::hasColumn('viewing_requests', 'legacy_uuid')
            && DB::table('viewing_requests')->whereNotNull('legacy_uuid')->exists()) {
            throw new RuntimeException('Cannot discard historical viewing-request evidence.');
        }

        // Check all destructive conditions BEFORE changing any schema.
        if (DB::table('saved_searches')->whereRaw('LENGTH(name) > 70')->exists()) {
            throw new RuntimeException('Cannot truncate saved-search names during rollback.');
        }
        Schema::table('viewing_requests', function (Blueprint $table): void {
            $table->dropForeign(['legacy_company_id']);
            $table->dropColumn([
                'legacy_uuid', 'legacy_company_id', 'legacy_preferred_date',
                'legacy_preferred_window', 'legacy_alternate_date',
                'legacy_alternate_window', 'legacy_scheduled_for',
                'legacy_original_status', 'legacy_company_note',
                'legacy_cancelled_at', 'legacy_completed_at',
            ]);
        });
        Schema::table('saved_searches', function (Blueprint $table): void {
            $table->dropColumn([
                'legacy_uuid', 'legacy_filter_evidence', 'legacy_review_required',
                'legacy_alert_frequency', 'legacy_alerts_enabled',
                'legacy_last_checked_at',
            ]);
        });
        Schema::table('saved_searches', function (Blueprint $table): void {
            $table->string('name', 70)->change();
        });
        Schema::dropIfExists('recent_listing_views');
        Schema::dropIfExists('comparison_items');
    }
};
