<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('servings');
            $table->timestamps();
            $table->index(['user_id', 'name']);
            // Required target for the ingredient's composite ownership foreign key.
            $table->unique(['id', 'user_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE recipes
                    ADD CONSTRAINT recipes_name_not_blank CHECK (name ~ '[^[:space:]]'),
                    ADD CONSTRAINT recipes_servings_positive CHECK (servings > 0)
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
