<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListProductsRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $products = $user->products()->orderBy('id')
            ->paginate($request->integer('per_page', 20));
        $products->appends($request->safe()->only('per_page'));

        return ProductResource::collection($products);
    }

    public function store(ProductRequest $request): ProductResource
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $product = $user->products()->create($request->validated());

        return new ProductResource($product->refresh());
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($product);
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product->refresh());
    }

    public function destroy(Product $product): Response
    {
        Gate::authorize('delete', $product);
        $product->delete();

        return response()->noContent()->header('Cache-Control', 'no-store, private');
    }
}
