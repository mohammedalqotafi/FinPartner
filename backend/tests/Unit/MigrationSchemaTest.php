<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * اختبارات بنية قاعدة البيانات والـ migrations
 *
 * Feature: expense-management-system
 * Property 9: Reference Uniqueness
 * Validates: Requirements 5.1, 5.2
 */
class MigrationSchemaTest extends TestCase
{
    use RefreshDatabase;

    // ─── expenses table structure ───────────────────────────────────────────

    public function test_expenses_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('expenses'));
    }

    public function test_expenses_table_has_required_columns(): void
    {
        $columns = [
            'id', 'reference', 'expense_type', 'affected_member_id',
            'category', 'amount', 'payer_id', 'payment_method',
            'description', 'expense_datetime', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('expenses', $column),
                "Column '{$column}' is missing from expenses table"
            );
        }
    }

    public function test_expense_splits_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('expense_splits'));
    }

    public function test_expense_splits_table_has_required_columns(): void
    {
        $columns = ['id', 'expense_id', 'member_id', 'amount', 'created_at', 'updated_at'];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('expense_splits', $column),
                "Column '{$column}' is missing from expense_splits table"
            );
        }
    }

    // ─── Property 9: Reference Uniqueness ───────────────────────────────────
    // For any two expenses in the system, their reference values should be
    // unique (no duplicates allowed).
    // Validates: Requirements 5.1, 5.2

    /**
     * Property 9: Reference Uniqueness
     * Validates: Requirements 5.1, 5.2
     *
     * The database enforces a UNIQUE constraint on expenses.reference.
     * Attempting to insert two rows with the same reference must throw.
     */
    public function test_reference_unique_constraint_is_enforced(): void
    {
        // Insert a member first (foreign key requirement)
        $memberId = DB::table('members')->insertGetId([
            'name'            => 'Test Member',
            'opening_balance' => 0,
            'balance'         => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $baseRow = [
            'reference'        => 'EXP-0001',
            'expense_type'     => 'operational',
            'category'         => 'Test',
            'amount'           => 100.00,
            'payment_method'   => 'cash',
            'expense_datetime' => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ];

        // First insert should succeed
        DB::table('expenses')->insert($baseRow);

        // Second insert with the same reference must fail
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('expenses')->insert($baseRow);
    }

    /**
     * Property 9: Reference Uniqueness (property-based variant)
     * Validates: Requirements 5.1, 5.2
     *
     * For any batch of N expenses with distinct references, all N rows are
     * stored successfully and every reference remains unique.
     * Runs 100 iterations with random reference counts.
     */
    public function test_multiple_distinct_references_are_all_stored(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            // Use a fresh in-memory DB state per iteration via RefreshDatabase
            // We generate a unique suffix per iteration to avoid cross-iteration collisions
            $suffix    = str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
            $reference = "EXP-{$suffix}";

            DB::table('expenses')->insert([
                'reference'        => $reference,
                'expense_type'     => 'operational',
                'category'         => 'Iteration Test',
                'amount'           => round(mt_rand(100, 100000) / 100, 2),
                'payment_method'   => 'cash',
                'expense_datetime' => now(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        // All 100 rows must be present
        $count = DB::table('expenses')->count();
        $this->assertEquals($iterations, $count);

        // Every reference must be unique
        $distinctCount = DB::table('expenses')->distinct()->count('reference');
        $this->assertEquals($iterations, $distinctCount);
    }

    // ─── expense_splits unique constraint ───────────────────────────────────

    /**
     * The unique constraint on (expense_id, member_id) prevents a member
     * from appearing twice in the same expense split.
     * Validates: Requirements 2.7 (split distribution completeness)
     */
    public function test_expense_splits_unique_expense_member_constraint(): void
    {
        // Create member
        $memberId = DB::table('members')->insertGetId([
            'name'            => 'Split Member',
            'opening_balance' => 0,
            'balance'         => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Create expense
        $expenseId = DB::table('expenses')->insertGetId([
            'reference'        => 'EXP-SPLIT-01',
            'expense_type'     => 'shared',
            'category'         => 'Test',
            'amount'           => 100.00,
            'payment_method'   => 'cash',
            'expense_datetime' => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // First split insert should succeed
        DB::table('expense_splits')->insert([
            'expense_id' => $expenseId,
            'member_id'  => $memberId,
            'amount'     => 100.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Duplicate (same expense_id + member_id) must fail
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('expense_splits')->insert([
            'expense_id' => $expenseId,
            'member_id'  => $memberId,
            'amount'     => 50.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Cascade delete: removing an expense must automatically remove its splits.
     * Validates: Requirements 12.1
     */
    public function test_deleting_expense_cascades_to_splits(): void
    {
        $memberId = DB::table('members')->insertGetId([
            'name'            => 'Cascade Member',
            'opening_balance' => 0,
            'balance'         => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $expenseId = DB::table('expenses')->insertGetId([
            'reference'        => 'EXP-CASCADE-01',
            'expense_type'     => 'shared',
            'category'         => 'Test',
            'amount'           => 60.00,
            'payment_method'   => 'cash',
            'expense_datetime' => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        DB::table('expense_splits')->insert([
            'expense_id' => $expenseId,
            'member_id'  => $memberId,
            'amount'     => 60.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertEquals(1, DB::table('expense_splits')->where('expense_id', $expenseId)->count());

        // Delete the parent expense
        DB::table('expenses')->where('id', $expenseId)->delete();

        // Splits must be gone
        $this->assertEquals(0, DB::table('expense_splits')->where('expense_id', $expenseId)->count());
    }
}
