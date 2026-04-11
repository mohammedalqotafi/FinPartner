<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseSplit>
 *
 * Requirements: 20.1, 20.2
 */
class ExpenseSplitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory()->shared(),
            'member_id'  => Member::factory(),
            'amount'     => round(fake()->randomFloat(2, 1, 500), 2),
        ];
    }

    /**
     * تقسيم بمبلغ محدد
     */
    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }

    /**
     * تقسيم لعضو محدد
     */
    public function forMember(int $memberId): static
    {
        return $this->state(fn (array $attributes) => [
            'member_id' => $memberId,
        ]);
    }

    /**
     * تقسيم لمصروف محدد
     */
    public function forExpense(int $expenseId): static
    {
        return $this->state(fn (array $attributes) => [
            'expense_id' => $expenseId,
        ]);
    }
}
