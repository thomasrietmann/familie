<?php

namespace Database\Factories;

use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChildFactory extends Factory
{
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->optional()->lastName(),
            'birthdate' => fake()->dateTimeBetween('-12 years', '-2 years'),
            'notes' => null,
        ];
    }
}
