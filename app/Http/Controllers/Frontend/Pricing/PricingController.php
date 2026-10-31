<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Pricing;

use App\Models\CommercialPlan;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class PricingController
{
    public function __invoke(): View
    {
        return view('frontend.pricing.index', [
            'plans' => Schema::hasTable('commercial_plans')
                ? CommercialPlan::query()->published()->orderBy('sort_order')->orderBy('name')->get()
                : collect(),
        ]);
    }
}
