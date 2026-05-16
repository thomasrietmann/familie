<?php

namespace Database\Factories;

use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

class FamilyEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'starts_at' => fake()->dateTimeBetween('now', '+2 months'),
            'ends_at' => null,
            'all_day' => false,
            'location' => fake()->optional()->city(),
            'category' => fake()->randomElement(['family_trip', 'playdate', 'birthday', 'school', 'childcare', 'medical', 'sport', 'holiday', 'meeting', 'other']),
            'visibility' => 'family',
            'owner_type' => 'family',
            'owner_id' => null,
            'status' => fake()->randomElement(['planned', 'confirmed']),
            'source' => 'manual',
        ];
    }
}
