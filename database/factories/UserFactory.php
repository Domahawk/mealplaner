<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Product owner fixture',
            'email' => 'factory-'.Str::uuid().'@example.test',
            'password' => static::$password ??= Hash::make('factory-password-fixture-123'),
        ];
    }
}
