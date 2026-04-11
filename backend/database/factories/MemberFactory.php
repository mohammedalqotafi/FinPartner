<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $openingBalance = round(fake()->randomFloat(2, 0, 5000), 2);

        return [
            'name'            => fake()->name(),
            'email'           => fake()->unique()->safeEmail(),
            'phone'           => fake()->numerify('05########'),
            'opening_balance' => $openingBalance,
            'balance'         => $openingBalance,
            'join_date'       => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
        ];
    }

    /**
     * عضو بدون رصيد افتتاحي
     */
    public function withZeroBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'opening_balance' => 0.00,
            'balance'         => 0.00,
        ]);
    }

    /**
     * عضو برصيد افتتاحي محدد
     */
    public function withOpeningBalance(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'opening_balance' => $amount,
            'balance'         => $amount,
        ]);
    }
}
