<?php

namespace Tests\Unit;

use App\Models\Expense;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختبارات Expense Model
 *
 * Feature: expense-management-system
 * Property 9: Reference Uniqueness
 * Validates: Requirements 5.1, 5.2
 */
class ExpenseModelTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helper ──────────────────────────────────────────────────────────────

    /**
     * إنشاء عضو بسيط لتلبية foreign key constraints
     */
    private function createMember(): Member
    {
        return Member::create([
            'name'            => 'Test Member',
            'opening_balance' => 0,
            'balance'         => 0,
        ]);
    }

    /**
     * إنشاء مصروف بـ reference محدد
     */
    private function createExpenseWithReference(string $reference): Expense
    {
        return Expense::create([
            'reference'        => $reference,
            'expense_type'     => 'operational',
            'category'         => 'Test',
            'amount'           => 100.00,
            'payment_method'   => 'cash',
            'expense_datetime' => now(),
        ]);
    }

    // ─── Property 9: Reference Uniqueness ────────────────────────────────────
    // For any two expenses in the system, their reference values should be
    // unique (no duplicates allowed).
    // Validates: Requirements 5.1, 5.2

    /**
     * Property 9: Reference Uniqueness
     * Validates: Requirements 5.1, 5.2
     *
     * For any number of consecutive calls to generateReference() on a fresh
     * database, every returned value must follow the EXP-XXXX format.
     * Runs 100 iterations.
     */
    public function test_generate_reference_always_returns_exp_format(): void
    {
        // Feature: expense-management-system, Property 9: Reference Uniqueness
        $pattern = '/^EXP-\d{4}$/';

        for ($i = 0; $i < 100; $i++) {
            $reference = Expense::generateReference();
            $this->assertMatchesRegularExpression(
                $pattern,
                $reference,
                "Generated reference '{$reference}' does not match EXP-XXXX format"
            );
        }
    }

    /**
     * Property 9: Reference Uniqueness
     * Validates: Requirements 5.1, 5.2
     *
     * For any set of N expenses already persisted, generateReference() must
     * return a value that does not collide with any existing reference.
     * Runs 100 iterations, each time pre-populating the DB with a known reference.
     */
    public function test_generate_reference_never_collides_with_existing(): void
    {
        // Feature: expense-management-system, Property 9: Reference Uniqueness

        for ($i = 0; $i < 100; $i++) {
            // Pre-occupy a reference so the generator must avoid it
            $occupied = 'EXP-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
            $this->createExpenseWithReference($occupied);

            $generated = Expense::generateReference();

            $this->assertNotEquals(
                $occupied,
                $generated,
                "generateReference() returned an already-existing reference: {$occupied}"
            );

            // Verify the generated reference is also not in the DB
            $this->assertFalse(
                Expense::where('reference', $generated)->exists(),
                "generateReference() returned a reference that already exists in DB: {$generated}"
            );
        }
    }

    /**
     * Property 9: Reference Uniqueness
     * Validates: Requirements 5.1, 5.2
     *
     * For any batch of N expenses created using generateReference(), all
     * references in the batch must be distinct.
     */
    public function test_batch_of_generated_references_are_all_unique(): void
    {
        // Feature: expense-management-system, Property 9: Reference Uniqueness
        $batchSize  = 50; // keep within 9999 pool
        $references = [];

        for ($i = 0; $i < $batchSize; $i++) {
            $ref = Expense::generateReference();
            $this->createExpenseWithReference($ref);
            $references[] = $ref;
        }

        $unique = array_unique($references);
        $this->assertCount(
            $batchSize,
            $unique,
            'Duplicate references were generated in a batch'
        );
    }
}
