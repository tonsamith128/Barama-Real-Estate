<?php

declare(strict_types=1);

namespace App\Http\Requests\Frontend\Companies;

use App\Domain\RealEstate\MarketplaceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BrowseCompaniesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(MarketplaceStatus::COMPANY_TYPES)],
            'sort' => ['nullable', Rule::in(['name', 'newest'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
