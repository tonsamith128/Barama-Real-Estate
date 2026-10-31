<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\BillingInvoice;
use App\Models\BillingOrder;
use App\Models\BillingTaxReview;
use App\Models\CommercialPlan;
use App\Models\Company;
use App\Models\User;
use App\Services\Billing\BillingReleaseReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BillingReleaseReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function review(): BillingTaxReview
    {
        $company = Company::query()->create([
            'name' => 'QA Global Property', 'slug' => 'global-qa',
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plan = CommercialPlan::query()->create([
            'name' => 'QA Plan', 'slug' => 'qa-plan', 'description' => 'QA',
            'price_cents' => 2500, 'currency' => 'USD',
            'billing_period' => 'once', 'listing_limit' => 4, 'is_active' => true,
        ]);
        $order = BillingOrder::query()->create([
            'company_id' => $company->id,
            'commercial_plan_id' => $plan->id,
            'requested_by_user_id' => $user->id,
            'reference' => '00000000-0000-4000-8000-000000009001',
            'gateway' => 'stripe_test', 'status' => 'paid',
            'plan_name' => 'QA Plan', 'amount_cents' => 2500,
            'currency' => 'USD', 'billing_period' => 'once',
            'listing_limit' => 4, 'paid_at' => now(),
        ]);
        $invoice = BillingInvoice::query()->create([
            'billing_order_id' => $order->id, 'invoice_number' => 'BRM-QA-9001',
            'status' => 'paid', 'total_cents' => 2500,
            'currency' => 'USD', 'paid_at' => now(),
        ]);
        return BillingTaxReview::query()->create([
            'billing_invoice_id' => $invoice->id, 'status' => 'unreviewed',
        ]);
    }

    public function test_release_report_cannot_approve_live_payments_from_local_ci(): void
    {
        $report = app(BillingReleaseReadiness::class)->report();
        $this->assertFalse($report['live_ready']);
        $this->assertSame('not_verified', $report['external_gates']['merchant_eligibility']['status']);
        $this->assertSame('not_verified', $report['external_gates']['provider_sandbox']['status']);
        $this->assertArrayHasKey('table_company_billing_profiles', $report['local_checks']);
        $this->assertArrayHasKey('https_origin', $report['local_checks']);
        $this->assertArrayNotHasKey('secret_key', $report);
        $this->artisan('billing:release-check --json')->assertExitCode(0);
        $this->artisan('billing:release-check --strict --json')->assertExitCode(2);
    }

    public function test_only_super_admin_can_write_review_notes_without_changing_invoice_amounts(): void
    {
        $review = $this->review();
        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $payload = [
            'status' => 'needs_specialist', 'jurisdiction' => 'KH',
            'review_note' => 'Professional tax registration and invoice classification require review.',
        ];
        $this->actingAs($ordinary)->patch(route('admin.finance.tax-reviews.update', $review), $payload)
            ->assertForbidden();
        $this->assertSame('unreviewed', $review->fresh()->status);

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->forceFill(['system_role' => 'super_admin'])->save();
        $this->actingAs($admin)->patch(route('admin.finance.tax-reviews.update', $review), $payload)
            ->assertRedirect(route('admin.finance.reconciliation.index'));
        $this->assertSame('needs_specialist', $review->fresh()->status);
        $this->assertSame('KH', $review->fresh()->jurisdiction);
        $this->assertSame(2500, $review->fresh()->invoice->total_cents);
        $this->assertSame('paid', $review->fresh()->invoice->status);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'billing.tax_review.recorded', 'subject_id' => $review->id,
        ]);
        $this->get(route('admin.finance.reconciliation.index'))
            ->assertOk()->assertSee('Live payments are')->assertSee('Specialist review required');
    }

    public function test_tax_review_validates_status_jurisdiction_and_review_details(): void
    {
        $review = $this->review();
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->forceFill(['system_role' => 'super_admin'])->save();
        $this->actingAs($admin)->patch(route('admin.finance.tax-reviews.update', $review), [
            'status' => 'approved_for_live', 'jurisdiction' => 'ZZ', 'review_note' => 'short',
        ])->assertSessionHasErrors(['status', 'jurisdiction', 'review_note']);
        $this->assertSame('unreviewed', $review->fresh()->status);
    }

    public function test_customer_billing_strings_have_all_required_locales_without_tax_approval(): void
    {
        $locales = array_keys(\App\Services\Billing\GlobalBillingPreferences::LOCALES);
        foreach ($locales as $locale) {
            $this->assertNotSame('billing.receipt', trans('billing.receipt', [], $locale));
            $this->assertNotSame('billing.receipt_intro', trans('billing.receipt_intro', [], $locale));
            $this->assertNotSame('billing.profile_title', trans('billing.profile_title', [], $locale));
        }
        $this->assertSame('ការបញ្ជាក់ការទូទាត់', trans('billing.receipt', [], 'km'));
        $this->assertSame('Confirmation de paiement', trans('billing.receipt', [], 'fr'));
    }
}
