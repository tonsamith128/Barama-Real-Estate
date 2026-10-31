<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Pricing;

use App\Models\CommercialPlan;
use App\Services\Billing\GlobalBillingPreferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class PricingController
{
    public function __invoke(Request $request): View
    {
        $requested = $request->query('lang', 'en');
        $locale = is_string($requested) && array_key_exists($requested, GlobalBillingPreferences::LOCALES)
            ? $requested : 'en';
        app()->setLocale($locale);

        return view('frontend.pricing.index', [
            'billingLocale' => $locale,
            'availableLanguages' => GlobalBillingPreferences::LOCALES,
            'plans' => Schema::hasTable('commercial_plans')
                ? CommercialPlan::query()->published()->orderBy('sort_order')->orderBy('name')->get()
                : collect(),
        ]);
    }
}
