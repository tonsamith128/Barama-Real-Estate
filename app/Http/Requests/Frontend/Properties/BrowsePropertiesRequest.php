<?php

declare(strict_types=1);

namespace App\Http\Requests\Frontend\Properties;

use App\Domain\RealEstate\MarketplaceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BrowsePropertiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'offer' => ['nullable', Rule::in(['sale', 'rent'])],
            'type' => ['nullable', Rule::in(MarketplaceStatus::PROPERTY_TYPES)],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'price_asc', 'price_desc'])],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:99999999999999', 'gte:min_price'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
