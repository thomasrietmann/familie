<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FamilyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Familie '.fake()->lastName(),
            'owner_user_id' => User::factory(),
            'public_token_enabled' => false,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
