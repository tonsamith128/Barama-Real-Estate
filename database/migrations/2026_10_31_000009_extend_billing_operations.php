<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_orders', function (Blueprint $table): void {
            $table->string('checkout_mode', 20)->default('payment');
            $table->string('gateway_subscription_id', 190)->nullable()->index();
        });
        Schema::table('company_subscriptions', function (Blueprint $table): void {
            $table->string('gateway_subscription_id', 190)->nullable()->unique();
            $table->string('gateway_customer_id', 190)->nullable();
        });

        Schema::create('billing_renewals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_order_id')->unique()->constrained('billing_orders')->restrictOnDelete();
            $table->string('gateway_invoice_id', 190)->unique();
            $table->string('gateway_subscription_id', 190)->index();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamps();
        });

        Schema::create('billing_disputes', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway_dispute_id', 190)->unique();
            $table->foreignId('billing_payment_id')->nullable()->constrained('billing_payments')->restrictOnDelete();
            $table->string('gateway_charge_id', 190)->nullable();
            $table->string('gateway_payment_id', 190)->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('status', 32)->default('open');
            $table->boolean('needs_reconciliation')->default(true);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_tax_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_invoice_id')->unique()->constrained('billing_invoices')->restrictOnDelete();
            $table->string('status', 24)->default('unreviewed');
            $table->string('jurisdiction', 80)->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_tax_reviews');
        Schema::dropIfExists('billing_disputes');
        Schema::dropIfExists('billing_renewals');
        Schema::table('company_subscriptions', function (Blueprint $table): void {
            $table->dropUnique(['gateway_subscription_id']);
            $table->dropColumn(['gateway_subscription_id', 'gateway_customer_id']);
        });
        Schema::table('billing_orders', function (Blueprint $table): void {
            $table->dropIndex(['gateway_subscription_id']);
            $table->dropColumn(['checkout_mode', 'gateway_subscription_id']);
        });
    }
};
