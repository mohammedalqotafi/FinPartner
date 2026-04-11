<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 *
 * Requirements: 20.1, 20.2
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     * الحالة الافتراضية: مصروف تشغيلي (operational)
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference'        => Expense::generateReference(),
            'expense_type'     => 'operational',
            'category'         => fake()->randomElement(['غيارات', 'رواتب', 'إيجار', 'مرافق', 'تسويق', 'أخرى']),
            'amount'           => round(fake()->randomFloat(2, 1, 5000), 2),
            'payer_id'         => null,
            'affected_member_id' => null,
            'payment_method'   => fake()->randomElement(['cash', 'transfer']),
            'description'      => fake()->optional(0.5)->sentence(),
            'expense_datetime' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    // ─── States ───────────────────────────────────────────────────────────────

    /**
     * مصروف تشغيلي (Operational) - بدون تخصيص لأعضاء
     * Requirements: 1.4
     */
    public function operational(): static
    {
        return $this->state(fn (array $attributes) => [
            'expense_type'       => 'operational',
            'affected_member_id' => null,
        ]);
    }

    /**
     * مصروف شخصي (Personal) - مخصص لعضو واحد
     * Requirements: 1.3
     */
    public function personal(?int $memberId = null): static
    {
        return $this->state(function (array $attributes) use ($memberId) {
            $affectedMemberId = $memberId ?? Member::factory()->create()->id;

            return [
                'expense_type'       => 'personal',
                'affected_member_id' => $affectedMemberId,
            ];
        });
    }

    /**
     * مصروف مشترك (Shared) - يتطلب إنشاء expense_splits بشكل منفصل
     * Requirements: 1.2, 2.7
     */
    public function shared(): static
    {
        return $this->state(fn (array $attributes) => [
            'expense_type'       => 'shared',
            'affected_member_id' => null,
        ]);
    }

    /**
     * مصروف مع دافع محدد (عضو دفع المصروف)
     * Requirements: 3.1
     */
    public function withPayer(?int $payerId = null): static
    {
        return $this->state(function (array $attributes) use ($payerId) {
            return [
                'payer_id' => $payerId ?? Member::factory()->create()->id,
            ];
        });
    }

    /**
     * مصروف بمبلغ محدد
     */
    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }
}
