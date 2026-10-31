<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Domain\RealEstate\MarketplaceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasVerifiedEmail();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:70'],
            'q' => ['nullable', 'string', 'max:100'],
            'offer' => ['nullable', Rule::in(['sale', 'rent'])],
            'type' => ['nullable', Rule::in(MarketplaceStatus::PROPERTY_TYPES)],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'price_asc', 'price_desc'])],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:99999999999999', ...($this->filled('min_price') ? ['gte:min_price'] : [])],
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();
        unset($validated['name']);
        return array_filter($validated, static fn ($value): bool => $value !== '' && $value !== null);
    }
}
