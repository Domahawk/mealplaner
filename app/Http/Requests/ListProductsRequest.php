<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('viewAny', Product::class);

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $rules = [
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['bail', 'sometimes', 'nullable', 'string', 'max:255', 'not_regex:/\x00/'],
        ];

        foreach (['calories' => '999999.99', 'protein' => '100', 'carbohydrates' => '100', 'fat' => '100'] as $nutrient => $maximum) {
            $minimumField = $nutrient.'_min';
            $rules[$minimumField] = ['bail', 'sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.$maximum];
            $rules[$nutrient.'_max'] = [
                'bail', 'sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.$maximum,
                Rule::when(is_numeric($this->input($minimumField)), ['gte:'.$minimumField]),
            ];
        }

        return $rules;
    }
}
