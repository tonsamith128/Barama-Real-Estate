<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_billing_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->restrictOnDelete();
            $table->string('billing_name', 160);
            $table->string('billing_email', 190);
            $table->char('country_code', 2);
            $table->string('address_line', 190)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('preferred_locale', 8)->default('en');
            $table->char('preferred_currency', 3)->default('USD');
            $table->string('timezone', 64)->default('UTC');
            $table->timestamps();
        });
        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->string('customer_billing_name', 160)->nullable();
            $table->char('customer_country_code', 2)->nullable();
        });
        Schema::table('company_subscriptions', function (Blueprint $table): void {
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancellation_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['cancel_at_period_end', 'cancellation_requested_at']);
        });
        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->dropColumn(['customer_billing_name', 'customer_country_code']);
        });
        Schema::dropIfExists('company_billing_profiles');
    }
};
