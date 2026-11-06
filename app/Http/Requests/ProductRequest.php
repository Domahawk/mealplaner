<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            Gate::authorize('create', Product::class);
        } else {
            Gate::authorize('update', $this->route('product'));
        }

        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        // PATCH preserves omitted fields; POST and PUT require all core nutrition.
        $required = $this->isMethod('PATCH') ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => ['bail', ...$required, 'string', 'max:255', 'regex:/\S/u', 'not_regex:/\x00/'],
            'brand' => ['bail', 'sometimes', 'nullable', 'string', 'max:255', 'regex:/\S/u', 'not_regex:/\x00/'],
            'package_weight_g' => ['bail', 'sometimes', 'nullable', 'numeric', 'decimal:0,3', 'gt:0', 'max:9999999.999'],
            'calories_per_100g' => ['bail', ...$required, 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'protein_per_100g' => ['bail', ...$required, 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'carbohydrates_per_100g' => ['bail', ...$required, 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'fat_per_100g' => ['bail', ...$required, 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'user_id' => ['missing'],
            'owner_id' => ['missing'],
            'id' => ['missing'],
            'created_at' => ['missing'],
            'updated_at' => ['missing'],
        ];
    }
}
