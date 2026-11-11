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
        Schema::table('viewing_requests', function (Blueprint $table): void {
            $table->dateTime('proposed_at')->nullable();
            $table->dateTime('proposed_expires_at')->nullable();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reschedule_note', 1000)->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('review_status', 24)->default('clear')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
        });

        Schema::create('viewing_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('viewing_request_id')->constrained('viewing_requests')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_kind', 24);
            $table->string('event_type', 48);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->dateTime('previous_time')->nullable();
            $table->dateTime('new_time')->nullable();
            $table->string('reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['viewing_request_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('viewing_events') && DB::table('viewing_events')->exists()) {
            throw new RuntimeException('Cannot discard immutable viewing decision history.');
        }
        if (Schema::hasColumn('viewing_requests', 'review_status')
            && DB::table('viewing_requests')->where(function ($query): void {
                $query->where('review_status', '<>', 'clear')
                    ->orWhereNotNull('proposed_at')
                    ->orWhereNotNull('confirmed_at')
                    ->orWhereNotNull('cancelled_at')
                    ->orWhereNotNull('reviewed_at');
            })->exists()) {
            throw new RuntimeException('Cannot discard pending or completed viewing lifecycle decisions.');
        }
        Schema::dropIfExists('viewing_events');
        Schema::table('viewing_requests', function (Blueprint $table): void {
            $table->dropForeign(['proposed_by']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn([
                'proposed_at', 'proposed_expires_at', 'proposed_by',
                'reschedule_note', 'confirmed_at', 'cancelled_at',
                'review_status', 'reviewed_by', 'reviewed_at',
            ]);
        });
    }
};
