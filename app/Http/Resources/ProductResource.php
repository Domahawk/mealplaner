<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->header('Cache-Control', 'no-store, private');
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'brand' => $this->brand,
            'package_weight_g' => $this->package_weight_g,
            'calories_per_100g' => $this->calories_per_100g,
            'protein_per_100g' => $this->protein_per_100g,
            'carbohydrates_per_100g' => $this->carbohydrates_per_100g,
            'fat_per_100g' => $this->fat_per_100g,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
