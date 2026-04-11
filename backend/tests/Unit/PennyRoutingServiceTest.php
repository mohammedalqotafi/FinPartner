<?php

namespace Tests\Unit;

use App\Services\PennyRoutingService;
use Tests\TestCase;

/**
 * اختبارات خوارزمية Penny Routing
 *
 * Feature: expense-management-system
 * Property 3: Equal Split Mathematical Accuracy
 * Property 4: Penny Routing Determinism
 * Validates: Requirements 2.1, 2.2, 2.3
 */
class PennyRoutingServiceTest extends TestCase
{
    private PennyRoutingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PennyRoutingService();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * توليد قائمة معرّفات أعضاء وهمية
     */
    private function makeMemberIds(int $count): array
    {
        return range(1, $count);
    }

    /**
     * توليد reference عشوائي بصيغة EXP-XXXX
     */
    private function randomReference(): string
    {
        return 'EXP-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    // ─── Property 3: Equal Split Mathematical Accuracy ────────────────────────
    // For any shared expense with equal split type, the sum of all member splits
    // should exactly equal the total expense amount with zero remainder.
    // Validates: Requirements 2.1, 2.2

    /**
     * Property 3: Equal Split Mathematical Accuracy
     * Validates: Requirements 2.1, 2.2
     *
     * For any total amount and any number of members (2–10), the sum of all
     * calculated splits must exactly equal the total amount.
     * Runs 100 iterations with random amounts and member counts.
     */
    public function test_split_sum_always_equals_total_amount(): void
    {
        // Feature: expense-management-system, Property 3: Equal Split Mathematical Accuracy

        for ($i = 0; $i < 100; $i++) {
            $totalAmount = round(mt_rand(1, 1000000) / 100, 2); // 0.01 – 10000.00
            $memberCount = mt_rand(2, 10);
            $memberIds   = $this->makeMemberIds($memberCount);
            $reference   = $this->randomReference();

            $splits = $this->service->calculateEqualSplits($totalAmount, $memberIds, $reference);

            // مجموع الحصص يجب أن يساوي المبلغ الإجمالي بدقة
            $splitSum = array_sum($splits);
            $this->assertEquals(
                (int) round($totalAmount * 100),
                (int) round($splitSum * 100),
                "Iteration {$i}: Split sum ({$splitSum}) does not equal total ({$totalAmount}) "
                . "for {$memberCount} members with reference {$reference}"
            );
        }
    }

    /**
     * Property 3: Equal Split Mathematical Accuracy — verifySplitSum helper
     * Validates: Requirements 2.1, 2.2
     *
     * The verifySplitSum() method must return true for any valid equal split.
     * Runs 100 iterations.
     */
    public function test_verify_split_sum_returns_true_for_valid_splits(): void
    {
        // Feature: expense-management-system, Property 3: Equal Split Mathematical Accuracy

        for ($i = 0; $i < 100; $i++) {
            $totalAmount = round(mt_rand(1, 1000000) / 100, 2);
            $memberCount = mt_rand(2, 10);
            $memberIds   = $this->makeMemberIds($memberCount);
            $reference   = $this->randomReference();

            $splits = $this->service->calculateEqualSplits($totalAmount, $memberIds, $reference);

            $this->assertTrue(
                $this->service->verifySplitSum($splits, $totalAmount),
                "Iteration {$i}: verifySplitSum() returned false for a valid equal split "
                . "(total={$totalAmount}, members={$memberCount})"
            );
        }
    }

    /**
     * Property 3: Equal Split Mathematical Accuracy — each share within ±0.01 of base
     * Validates: Requirements 2.1, 2.2
     *
     * For any member, their share should differ from the base share by at most 0.01
     * (one penny). Runs 100 iterations.
     */
    public function test_each_split_differs_from_base_by_at_most_one_penny(): void
    {
        // Feature: expense-management-system, Property 3: Equal Split Mathematical Accuracy

        for ($i = 0; $i < 100; $i++) {
            $totalAmount = round(mt_rand(1, 1000000) / 100, 2);
            $memberCount = mt_rand(2, 10);
            $memberIds   = $this->makeMemberIds($memberCount);
            $reference   = $this->randomReference();

            $splits    = $this->service->calculateEqualSplits($totalAmount, $memberIds, $reference);
            $baseShare = floor(($totalAmount * 100) / $memberCount) / 100;

            foreach ($splits as $memberId => $amount) {
                $diff = round(abs($amount - $baseShare) * 100); // بالسنتات
                $this->assertLessThanOrEqual(
                    1,
                    $diff,
                    "Iteration {$i}: Member {$memberId} share ({$amount}) differs from base "
                    . "({$baseShare}) by more than 1 penny"
                );
            }
        }
    }

    /**
     * Property 3: Equal Split Mathematical Accuracy — number of splits equals member count
     * Validates: Requirements 2.7
     *
     * The number of returned splits must equal the number of input members.
     * Runs 100 iterations.
     */
    public function test_number_of_splits_equals_member_count(): void
    {
        // Feature: expense-management-system, Property 3: Equal Split Mathematical Accuracy

        for ($i = 0; $i < 100; $i++) {
            $totalAmount = round(mt_rand(100, 100000) / 100, 2);
            $memberCount = mt_rand(2, 10);
            $memberIds   = $this->makeMemberIds($memberCount);
            $reference   = $this->randomReference();

            $splits = $this->service->calculateEqualSplits($totalAmount, $memberIds, $reference);

            $this->assertCount(
                $memberCount,
                $splits,
                "Iteration {$i}: Expected {$memberCount} splits, got " . count($splits)
            );
        }
    }

    // ─── Property 4: Penny Routing Determinism ────────────────────────────────
    // For any shared expense with equal split, applying the penny routing algorithm
    // twice with the same reference should produce identical split distributions.
    // Validates: Requirements 2.3

    /**
     * Property 4: Penny Routing Determinism
     * Validates: Requirements 2.3
     *
     * For any total amount, member list, and reference, calling
     * calculateEqualSplits() twice must return identical results.
     * Runs 100 iterations.
     */
    public function test_same_inputs_always_produce_same_splits(): void
    {
        // Feature: expense-management-system, Property 4: Penny Routing Determinism

        for ($i = 0; $i < 100; $i++) {
            $totalAmount = round(mt_rand(1, 1000000) / 100, 2);
            $memberCount = mt_rand(2, 10);
            $memberIds   = $this->makeMemberIds($memberCount);
            $reference   = $this->randomReference();

            $splits1 = $this->service->calculateEqualSplits($totalAmount, $memberIds, $reference);
            $splits2 = $this->service->calculateEqualSplits($totalAmount, $memberIds, $reference);

            $this->assertEquals(
                $splits1,
                $splits2,
                "Iteration {$i}: Same inputs produced different splits for reference {$reference}"
            );
        }
    }

    /**
     * Property 4: Penny Routing Determinism — different references produce different distributions
     * Validates: Requirements 2.3
     *
     * For a divisible-with-remainder amount, two different references should
     * (statistically) produce different penny assignments at least some of the time.
     * We verify that the offset function itself varies across references.
     * Runs 100 iterations.
     */
    public function test_different_references_produce_different_offsets(): void
    {
        // Feature: expense-management-system, Property 4: Penny Routing Determinism
        $memberCount = 7; // prime number to maximise remainder variation
        $offsets     = [];

        for ($i = 0; $i < 100; $i++) {
            $reference = 'EXP-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
            $offsets[] = $this->service->computeRecipientOffset($reference, $memberCount);
        }

        // All offsets must be in valid range [0, memberCount-1]
        foreach ($offsets as $idx => $offset) {
            $this->assertGreaterThanOrEqual(0, $offset, "Offset at index {$idx} is negative");
            $this->assertLessThan($memberCount, $offset, "Offset at index {$idx} >= memberCount");
        }

        // The set of offsets should not be all identical (distribution check)
        $uniqueOffsets = array_unique($offsets);
        $this->assertGreaterThan(
            1,
            count($uniqueOffsets),
            'All 100 references produced the same offset — hash distribution is broken'
        );
    }

    /**
     * Property 4: Penny Routing Determinism — computeRecipientOffset is deterministic
     * Validates: Requirements 2.3
     *
     * For any reference and count, calling computeRecipientOffset() twice must
     * return the same value. Runs 100 iterations.
     */
    public function test_compute_recipient_offset_is_deterministic(): void
    {
        // Feature: expense-management-system, Property 4: Penny Routing Determinism

        for ($i = 0; $i < 100; $i++) {
            $reference = $this->randomReference();
            $count     = mt_rand(2, 20);

            $offset1 = $this->service->computeRecipientOffset($reference, $count);
            $offset2 = $this->service->computeRecipientOffset($reference, $count);

            $this->assertSame(
                $offset1,
                $offset2,
                "Iteration {$i}: computeRecipientOffset() returned different values for "
                . "reference={$reference}, count={$count}"
            );
        }
    }

    // ─── Edge Cases ───────────────────────────────────────────────────────────

    /**
     * حالة حدية: مبلغ قابل للقسمة بالتساوي — لا يوجد باقٍ
     * Validates: Requirements 2.1
     */
    public function test_evenly_divisible_amount_has_no_remainder(): void
    {
        // 100.00 / 4 = 25.00 exactly — no penny routing needed
        $splits = $this->service->calculateEqualSplits(100.00, [1, 2, 3, 4], 'EXP-0001');

        foreach ($splits as $amount) {
            $this->assertEquals(25.00, $amount);
        }
    }

    /**
     * حالة حدية: عضو واحد يستلم المبلغ كاملاً
     * Validates: Requirements 2.1
     */
    public function test_single_member_receives_full_amount(): void
    {
        $splits = $this->service->calculateEqualSplits(99.99, [42], 'EXP-0001');

        $this->assertCount(1, $splits);
        $this->assertEquals(99.99, $splits[42]);
    }

    /**
     * حالة حدية: مبلغ صغير جداً (0.01) على عضوين
     * Validates: Requirements 2.1, 2.2
     */
    public function test_smallest_amount_split_between_two_members(): void
    {
        // 0.01 / 2 = 0.00 base, remainder = 1 cent → one member gets 0.01, other gets 0.00
        $splits = $this->service->calculateEqualSplits(0.01, [1, 2], 'EXP-0001');

        $this->assertCount(2, $splits);
        $this->assertEquals(1, (int) round(array_sum($splits) * 100)); // sum = 0.01
    }

    /**
     * حالة حدية: مبلغ كبير على عدد كبير من الأعضاء
     * Validates: Requirements 2.1, 2.2
     */
    public function test_large_amount_with_many_members(): void
    {
        $totalAmount = 9999.99;
        $memberIds   = range(1, 10);
        $reference   = 'EXP-9999';

        $splits = $this->service->calculateEqualSplits($totalAmount, $memberIds, $reference);

        $this->assertTrue(
            $this->service->verifySplitSum($splits, $totalAmount),
            'Large amount split sum does not match total'
        );
    }

    /**
     * استثناء: مبلغ صفر يرمي InvalidArgumentException
     */
    public function test_zero_amount_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->calculateEqualSplits(0.0, [1, 2], 'EXP-0001');
    }

    /**
     * استثناء: قائمة أعضاء فارغة ترمي InvalidArgumentException
     */
    public function test_empty_member_list_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->calculateEqualSplits(100.00, [], 'EXP-0001');
    }
}
