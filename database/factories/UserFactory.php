<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('R00t7414'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'is_active' => true,
            'setup_completed_at' => now(),
            'phone' => fake()->phoneNumber(),
            'designation' => fake()->jobTitle(),
            'city' => fake()->city(),
            'country' => fake()->country(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the user has a pending invitation (not yet activated).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'setup_completed_at' => null,
            'setup_token' => \Illuminate\Support\Str::random(64),
            'setup_token_sent_at' => now(),
        ]);
    }

    /**
     * Set a test avatar image.
     */
    public function withAvatar(): static
    {
        $avatars = [
            'batperson@192.webp',
            'superperson@192.webp',
            'spiderperson@192.webp',
            'averagebulk@192.webp',
        ];

        return $this->state(fn (array $attributes) => [
            'avatar' => 'images/test/avatars/'.$avatars[array_rand($avatars)],
        ]);
    }
}
