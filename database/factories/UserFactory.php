<?php

namespace Database\Factories;

use App\Support\MemberColorPalette;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'member_color' => fake()->randomElement(MemberColorPalette::keys()),
            'remember_token' => Str::random(10),
        ];
    }
}
