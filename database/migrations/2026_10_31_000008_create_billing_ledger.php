<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('commercial_plan_id')->constrained('commercial_plans')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('reference')->unique();
            $table->string('gateway', 24)->default('stripe_test');
            $table->string('status', 24)->default('pending');
            $table->string('plan_name', 120);
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('billing_period', 12);
            $table->unsignedInteger('listing_limit');
            $table->string('gateway_session_id', 190)->nullable()->unique();
            $table->string('gateway_payment_id', 190)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status', 'created_at']);
        });

        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_order_id')->unique()->constrained('billing_orders')->restrictOnDelete();
            $table->string('invoice_number', 60)->unique();
            $table->string('status', 24)->default('open');
            $table->unsignedBigInteger('total_cents');
            $table->unsignedBigInteger('refunded_cents')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_order_id')->unique()->constrained('billing_orders')->restrictOnDelete();
            $table->string('gateway', 24);
            $table->string('gateway_payment_id', 190)->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('refunded_cents')->default(0);
            $table->char('currency', 3);
            $table->string('status', 24)->default('paid');
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        Schema::create('company_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->restrictOnDelete();
            $table->foreignId('commercial_plan_id')->constrained('commercial_plans')->restrictOnDelete();
            $table->foreignId('billing_order_id')->unique()->constrained('billing_orders')->restrictOnDelete();
            $table->string('status', 24)->default('active');
            $table->string('billing_period', 12);
            $table->unsignedInteger('listing_limit');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'ends_at']);
        });

        Schema::create('billing_refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('billing_payment_id')->constrained('billing_payments')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('gateway_refund_id', 190)->nullable()->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->string('reason', 120);
            $table->string('status', 24)->default('requested');
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 24);
            $table->string('gateway_event_id', 190);
            $table->string('event_type', 120);
            $table->string('status', 24)->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'gateway_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
        Schema::dropIfExists('billing_refunds');
        Schema::dropIfExists('company_subscriptions');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('billing_orders');
    }
};
