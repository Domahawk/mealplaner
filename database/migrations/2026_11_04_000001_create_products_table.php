<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->decimal('package_weight_g', 10, 3)->nullable();
            $table->decimal('calories_per_100g', 8, 2);
            $table->decimal('protein_per_100g', 5, 2);
            $table->decimal('carbohydrates_per_100g', 5, 2);
            $table->decimal('fat_per_100g', 5, 2);
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });

        // PostgreSQL is authoritative; SQLite is only an optional model smoke test.
        // Laravel's Schema Builder has no CHECK constraint command.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE products
                    ADD CONSTRAINT products_name_not_blank CHECK (name ~ '[^[:space:]]'),
                    ADD CONSTRAINT products_brand_not_blank CHECK (brand IS NULL OR brand ~ '[^[:space:]]'),
                    ADD CONSTRAINT products_package_weight_valid CHECK (package_weight_g IS NULL OR package_weight_g > 0 AND package_weight_g <= 9999999.999),
                    ADD CONSTRAINT products_calories_valid CHECK (calories_per_100g BETWEEN 0 AND 999999.99),
                    ADD CONSTRAINT products_protein_valid CHECK (protein_per_100g BETWEEN 0 AND 100),
                    ADD CONSTRAINT products_carbohydrates_valid CHECK (carbohydrates_per_100g BETWEEN 0 AND 100),
                    ADD CONSTRAINT products_fat_valid CHECK (fat_per_100g BETWEEN 0 AND 100)
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
