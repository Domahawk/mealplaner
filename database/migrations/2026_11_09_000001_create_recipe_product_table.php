<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // A composite FK needs a unique target including the owner column.
            $table->unique(['id', 'user_id']);
        });
        Schema::create('recipe_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_id');
            $table->foreignId('product_id');
            $table->foreignId('user_id');
            $table->decimal('quantity_g', 10, 3);
            $table->timestamps();
            $table->unique(['recipe_id', 'product_id']);
            $table->index(['product_id', 'user_id']);
            $table->foreign(['recipe_id', 'user_id'], 'recipe_product_recipe_owner_foreign')
                ->references(['id', 'user_id'])->on('recipes')->cascadeOnDelete();
            $table->foreign(['product_id', 'user_id'], 'recipe_product_product_owner_foreign')
                ->references(['id', 'user_id'])->on('products')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE recipe_product
                    ADD CONSTRAINT recipe_product_quantity_positive CHECK (quantity_g > 0 AND quantity_g <= 9999999.999)
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_product');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['id', 'user_id']);
        });
    }
};
