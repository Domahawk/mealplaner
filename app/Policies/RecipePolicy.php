<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RecipePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Recipe $recipe): Response
    {
        return $this->ownership($user, $recipe);
    }

    public function update(User $user, Recipe $recipe): Response
    {
        return $this->ownership($user, $recipe);
    }

    public function delete(User $user, Recipe $recipe): Response
    {
        return $this->ownership($user, $recipe);
    }

    private function ownership(User $user, Recipe $recipe): Response
    {
        return $user->getKey() === $recipe->user_id ? Response::allow() : Response::denyAsNotFound();
    }
}
