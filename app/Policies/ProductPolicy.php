<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): Response
    {
        return $this->ownership($user, $product);
    }

    public function update(User $user, Product $product): Response
    {
        return $this->ownership($user, $product);
    }

    public function delete(User $user, Product $product): Response
    {
        return $this->ownership($user, $product);
    }

    private function ownership(User $user, Product $product): Response
    {
        return $user->getKey() === $product->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
