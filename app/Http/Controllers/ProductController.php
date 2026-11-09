<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListProductsRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $query = $user->products();
        $search = $request->validated('search');
        if (is_string($search) && $search !== '') {
            // Literal substring search; user input cannot introduce LIKE wildcards.
            $pattern = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(brand) LIKE LOWER(?) ESCAPE '!'", [$pattern]);
            });
        }
        foreach (['calories', 'protein', 'carbohydrates', 'fat'] as $nutrient) {
            foreach (['min' => '>=', 'max' => '<='] as $bound => $operator) {
                $value = $request->validated($nutrient.'_'.$bound);
                if ($value !== null) {
                    $query->where($nutrient.'_per_100g', $operator, $value);
                }
            }
        }
        $products = $query->orderBy('id')->paginate($request->integer('per_page', 20));
        $products->appends($request->safe()->except('page'));

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
        try {
            // The FK is authoritative, including an attachment racing with deletion.
            DB::transaction(fn () => $product->delete());
        } catch (QueryException $exception) {
            $recipeConstraint = in_array($exception->errorInfo[0] ?? null, ['23001', '23503'], true)
                && str_contains((string) ($exception->errorInfo[2] ?? ''), 'recipe_product_product_owner_foreign');
            // SQLite reports no constraint name; retain optional in-memory test support.
            $sqliteRecipeConstraint = DB::getDriverName() === 'sqlite'
                && ($exception->errorInfo[0] ?? null) === '23000' && $product->recipes()->exists();
            if (! $recipeConstraint && ! $sqliteRecipeConstraint) {
                throw $exception;
            }
            abort(409, 'This product is used in a recipe. Remove it from recipes before deleting it.');
        }

        return response()->noContent()->header('Cache-Control', 'no-store, private');
    }
}
