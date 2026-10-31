<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Models\CommercialPlan;
use App\Models\CompanyPlanInquiry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CommercialPlanController
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        abort_unless(Schema::hasTable('commercial_plans')
            && Schema::hasTable('commercial_plan_inquiries'), 503, 'Run pending database migrations.');
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.finance.plans.index', [
            'plans' => CommercialPlan::query()->orderBy('sort_order')->orderBy('name')->get(),
            'inquiries' => CompanyPlanInquiry::query()->with(['company', 'plan', 'requestedBy'])
                ->latest()->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $this->persist($request, null);
        return back()->with('notice', 'Plan saved. Only active plans are displayed publicly.');
    }

    public function update(Request $request, CommercialPlan $plan): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $this->persist($request, $plan);
        return back()->with('notice', 'Plan updated. Existing requests remain recorded.');
    }

    private function persist(Request $request, ?CommercialPlan $plan): void
    {
        $data = $request->validate([
            'slug' => [
                'required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('commercial_plans', 'slug')->ignore($plan?->getKey()),
            ],
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'regex:/^(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,2})?$/'],
            'billing_period' => ['required', Rule::in(['month', 'year', 'once'])],
            'listing_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $price = explode('.', (string) $data['price'], 2);
        $data['price_cents'] = ((int) $price[0] * 100)
            + (int) str_pad($price[1] ?? '', 2, '0');
        unset($data['price']);
        $data['is_active'] = $request->boolean('is_active');
        $data['currency'] = 'USD';

        DB::transaction(function () use ($plan, $data, $request): void {
            $created = $plan === null;
            $plan ??= new CommercialPlan();
            $plan->fill($data)->save();

            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->getKey(),
                'action' => $created ? 'finance.plan.created' : 'finance.plan.updated',
                'subject_type' => 'commercial_plan',
                'subject_id' => $plan->getKey(),
                'context' => json_encode(['active' => $plan->is_active], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
}
