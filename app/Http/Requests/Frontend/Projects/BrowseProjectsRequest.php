<?php

declare(strict_types=1);

namespace App\Http\Requests\Frontend\Projects;

use App\Domain\RealEstate\MarketplaceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BrowseProjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(MarketplaceStatus::PROJECT_TYPES)],
            'stage' => ['nullable', Rule::in(MarketplaceStatus::PROJECT_COMPLETION_STATES)],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
