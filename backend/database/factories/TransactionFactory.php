<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => Transaction::generateReference(),
            'member_id' => \App\Models\Member::factory(),
            'type' => $this->faker->randomElement(['deposit', 'withdraw', 'adjustment', 'transfer']),
            'amount' => $this->faker->randomFloat(2, 10, 5000),
            'transaction_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'note' => $this->faker->optional()->sentence(),
            'status' => 'completed',
            'balance_after' => 0,
        ];
    }

    /**
     * Indicate that the transaction is a deposit.
     */
    public function deposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'deposit',
        ]);
    }

    /**
     * Indicate that the transaction is a withdrawal.
     */
    public function withdraw(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'withdraw',
        ]);
    }

    /**
     * Indicate that the transaction is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the transaction is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
