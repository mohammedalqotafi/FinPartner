<?php

namespace Tests\Unit;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * اختبارات StoreExpenseRequest - التحقق من صحة بيانات المصروف
 *
 * Feature: expense-management-system
 * Property 1: Expense Type Validation
 * Property 2: Required Fields Based on Type
 * Property 11: Minimum Amount Validation
 * Validates: Requirements 1.1, 1.2, 1.3, 10.1, 10.2
 */
class StoreExpenseRequestTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * إنشاء عضو للاستخدام في الاختبارات
     */
    private function createMember(): Member
    {
        return Member::create([
            'name'            => 'Test Member ' . uniqid(),
            'opening_balance' => 0,
            'balance'         => 0,
        ]);
    }

    /**
     * بيانات مصروف صالحة كـ baseline
     */
    private function validBaseData(string $type = 'operational'): array
    {
        return [
            'expense_type'     => $type,
            'category'         => 'Test Category',
            'amount'           => 100.00,
            'payment_method'   => 'cash',
            'expense_datetime' => now()->toDateTimeString(),
        ];
    }

    /**
     * تشغيل التحقق باستخدام قواعد StoreExpenseRequest
     */
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new StoreExpenseRequest();
        // نحاكي الـ request بالبيانات المُدخلة
        $request->merge($data);
        $request->setMethod('POST');

        // نستخدم Validator مباشرة مع نفس القواعد
        return Validator::make($data, $request->rules(), $request->messages());
    }

    // ─── Property 1: Expense Type Validation ─────────────────────────────────
    // For any expense creation request, the system should accept only the three
    // valid expense types (shared, operational, personal) and reject any other values.
    // Validates: Requirements 1.1, 10.2

    /**
     * Property 1: Expense Type Validation
     * Validates: Requirements 1.1, 10.2
     *
     * For any of the three valid expense types, validation should pass.
     * Runs 100 iterations cycling through valid types.
     */
    public function test_valid_expense_types_pass_validation(): void
    {
        // Feature: expense-management-system, Property 1: Expense Type Validation
        $validTypes = ['shared', 'operational', 'personal'];
        $member     = $this->createMember();

        for ($i = 0; $i < 100; $i++) {
            $type = $validTypes[$i % 3];
            $data = $this->validBaseData($type);

            // shared يحتاج members و split_type
            if ($type === 'shared') {
                $data['split_type'] = 'equal';
                $data['members']    = [['id' => $member->id]];
            }

            // personal يحتاج affected_member_id
            if ($type === 'personal') {
                $data['affected_member_id'] = $member->id;
            }

            $validator = $this->validate($data);

            $this->assertFalse(
                $validator->fails(),
                "Iteration {$i}: Valid type '{$type}' should pass validation. Errors: "
                . json_encode($validator->errors()->toArray())
            );
        }
    }

    /**
     * Property 1: Expense Type Validation
     * Validates: Requirements 1.1, 10.2
     *
     * For any invalid expense type string, validation should fail with an error
     * on the expense_type field.
     * Runs 100 iterations with randomly generated invalid type strings.
     */
    public function test_invalid_expense_types_fail_validation(): void
    {
        // Feature: expense-management-system, Property 1: Expense Type Validation
        $invalidTypes = [
            'SHARED', 'Shared', 'OPERATIONAL', 'Personal', 'expense',
            'type', 'unknown', 'mixed', 'split', 'general',
            '', 'null', '0', '1', 'true', 'false',
            'shared_expense', 'personal_expense', 'op', 'ops',
        ];

        for ($i = 0; $i < 100; $i++) {
            $invalidType = $invalidTypes[$i % count($invalidTypes)] . ($i >= count($invalidTypes) ? "_$i" : '');
            $data        = $this->validBaseData($invalidType);

            $validator = $this->validate($data);

            $this->assertTrue(
                $validator->fails(),
                "Iteration {$i}: Invalid type '{$invalidType}' should fail validation"
            );

            $this->assertArrayHasKey(
                'expense_type',
                $validator->errors()->toArray(),
                "Iteration {$i}: Error should be on 'expense_type' field for type '{$invalidType}'"
            );
        }
    }

    /**
     * Property 1: Expense Type Validation - missing type
     * Validates: Requirements 1.1, 10.2
     *
     * When expense_type is missing entirely, validation should fail.
     */
    public function test_missing_expense_type_fails_validation(): void
    {
        // Feature: expense-management-system, Property 1: Expense Type Validation
        $data = $this->validBaseData();
        unset($data['expense_type']);

        $validator = $this->validate($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('expense_type', $validator->errors()->toArray());
    }

    // ─── Property 2: Required Fields Based on Type ────────────────────────────
    // For any expense with type 'shared', the system should require a non-empty
    // members list and split_type; for type 'personal', require affected_member_id.
    // Validates: Requirements 1.2, 1.3

    /**
     * Property 2: Required Fields Based on Type - shared requires members
     * Validates: Requirements 1.2
     *
     * For any shared expense without a members list, validation should fail.
     * Runs 100 iterations.
     */
    public function test_shared_expense_without_members_fails_validation(): void
    {
        // Feature: expense-management-system, Property 2: Required Fields Based on Type

        for ($i = 0; $i < 100; $i++) {
            $amount = round(mt_rand(100, 100000) / 100, 2);
            $data   = $this->validBaseData('shared');
            $data['amount']     = $amount;
            $data['split_type'] = 'equal';
            // members intentionally omitted

            $validator = $this->validate($data);

            $this->assertTrue(
                $validator->fails(),
                "Iteration {$i}: Shared expense without members should fail validation"
            );

            $this->assertArrayHasKey(
                'members',
                $validator->errors()->toArray(),
                "Iteration {$i}: Error should be on 'members' field"
            );
        }
    }

    /**
     * Property 2: Required Fields Based on Type - shared requires split_type
     * Validates: Requirements 1.2
     *
     * For any shared expense without split_type, validation should fail.
     * Runs 100 iterations.
     */
    public function test_shared_expense_without_split_type_fails_validation(): void
    {
        // Feature: expense-management-system, Property 2: Required Fields Based on Type
        $member = $this->createMember();

        for ($i = 0; $i < 100; $i++) {
            $amount = round(mt_rand(100, 100000) / 100, 2);
            $data   = $this->validBaseData('shared');
            $data['amount']  = $amount;
            $data['members'] = [['id' => $member->id]];
            // split_type intentionally omitted

            $validator = $this->validate($data);

            $this->assertTrue(
                $validator->fails(),
                "Iteration {$i}: Shared expense without split_type should fail validation"
            );

            $this->assertArrayHasKey(
                'split_type',
                $validator->errors()->toArray(),
                "Iteration {$i}: Error should be on 'split_type' field"
            );
        }
    }

    /**
     * Property 2: Required Fields Based on Type - personal requires affected_member_id
     * Validates: Requirements 1.3
     *
     * For any personal expense without affected_member_id, validation should fail.
     * Runs 100 iterations.
     */
    public function test_personal_expense_without_affected_member_fails_validation(): void
    {
        // Feature: expense-management-system, Property 2: Required Fields Based on Type

        for ($i = 0; $i < 100; $i++) {
            $amount = round(mt_rand(100, 100000) / 100, 2);
            $data   = $this->validBaseData('personal');
            $data['amount'] = $amount;
            // affected_member_id intentionally omitted

            $validator = $this->validate($data);

            $this->assertTrue(
                $validator->fails(),
                "Iteration {$i}: Personal expense without affected_member_id should fail validation"
            );

            $this->assertArrayHasKey(
                'affected_member_id',
                $validator->errors()->toArray(),
                "Iteration {$i}: Error should be on 'affected_member_id' field"
            );
        }
    }

    /**
     * Property 2: Required Fields Based on Type - operational needs no extra fields
     * Validates: Requirements 1.4
     *
     * For any operational expense with only base fields, validation should pass.
     * Runs 100 iterations with random amounts.
     */
    public function test_operational_expense_with_base_fields_passes_validation(): void
    {
        // Feature: expense-management-system, Property 2: Required Fields Based on Type

        for ($i = 0; $i < 100; $i++) {
            $amount = round(mt_rand(100, 100000) / 100, 2);
            $data   = $this->validBaseData('operational');
            $data['amount'] = $amount;

            $validator = $this->validate($data);

            $this->assertFalse(
                $validator->fails(),
                "Iteration {$i}: Operational expense with base fields should pass. Errors: "
                . json_encode($validator->errors()->toArray())
            );
        }
    }

    /**
     * Property 2: Required Fields Based on Type - shared with valid members passes
     * Validates: Requirements 1.2
     *
     * For any shared expense with members and split_type, validation should pass.
     * Runs 100 iterations.
     */
    public function test_shared_expense_with_required_fields_passes_validation(): void
    {
        // Feature: expense-management-system, Property 2: Required Fields Based on Type
        $member = $this->createMember();

        for ($i = 0; $i < 100; $i++) {
            $amount = round(mt_rand(100, 100000) / 100, 2);
            $data   = $this->validBaseData('shared');
            $data['amount']     = $amount;
            $data['split_type'] = 'equal';
            $data['members']    = [['id' => $member->id]];

            $validator = $this->validate($data);

            $this->assertFalse(
                $validator->fails(),
                "Iteration {$i}: Shared expense with required fields should pass. Errors: "
                . json_encode($validator->errors()->toArray())
            );
        }
    }

    // ─── Property 11: Minimum Amount Validation ───────────────────────────────
    // For any expense creation request, the system should reject amounts
    // less than or equal to 0.00.
    // Validates: Requirements 10.1

    /**
     * Property 11: Minimum Amount Validation - zero and negative amounts fail
     * Validates: Requirements 10.1
     *
     * For any amount <= 0.00, validation should fail with an error on 'amount'.
     * Runs 100 iterations with zero and negative values.
     */
    public function test_zero_and_negative_amounts_fail_validation(): void
    {
        // Feature: expense-management-system, Property 11: Minimum Amount Validation

        $invalidAmounts = [0, 0.0, -0.01, -1, -100, -999.99, -0.001];

        for ($i = 0; $i < 100; $i++) {
            $amount = $invalidAmounts[$i % count($invalidAmounts)];
            // For negative values, use the raw value (not abs)
            $data           = $this->validBaseData('operational');
            $data['amount'] = $amount;

            // Bypass prepareForValidation by using Validator directly
            $request = new StoreExpenseRequest();
            $validator = Validator::make($data, $request->rules(), $request->messages());

            $this->assertTrue(
                $validator->fails(),
                "Iteration {$i}: Amount {$amount} should fail validation"
            );

            $this->assertArrayHasKey(
                'amount',
                $validator->errors()->toArray(),
                "Iteration {$i}: Error should be on 'amount' field for amount {$amount}"
            );
        }
    }

    /**
     * Property 11: Minimum Amount Validation - amounts above minimum pass
     * Validates: Requirements 10.1
     *
     * For any amount >= 0.01, validation should pass the amount check.
     * Runs 100 iterations with valid positive amounts.
     */
    public function test_valid_positive_amounts_pass_validation(): void
    {
        // Feature: expense-management-system, Property 11: Minimum Amount Validation

        for ($i = 0; $i < 100; $i++) {
            // Generate amounts from 0.01 to 999999.99
            $amount = round(mt_rand(1, 99999999) / 100, 2);
            $data   = $this->validBaseData('operational');
            $data['amount'] = $amount;

            $validator = $this->validate($data);

            $this->assertFalse(
                isset($validator->errors()->toArray()['amount']),
                "Iteration {$i}: Amount {$amount} should not produce an amount error. Errors: "
                . json_encode($validator->errors()->toArray())
            );
        }
    }

    /**
     * Property 11: Minimum Amount Validation - boundary value 0.01 passes
     * Validates: Requirements 10.1
     *
     * The minimum valid amount (0.01) should pass validation.
     */
    public function test_minimum_valid_amount_passes_validation(): void
    {
        // Feature: expense-management-system, Property 11: Minimum Amount Validation
        $data           = $this->validBaseData('operational');
        $data['amount'] = 0.01;

        $validator = $this->validate($data);

        $this->assertFalse(
            isset($validator->errors()->toArray()['amount']),
            'Amount 0.01 (minimum valid) should pass validation'
        );
    }

    // ─── Additional Validation Tests ─────────────────────────────────────────

    /**
     * التحقق من أن payer_id يجب أن يكون عضواً موجوداً
     * Validates: Requirements 10.5
     */
    public function test_nonexistent_payer_id_fails_validation(): void
    {
        $data             = $this->validBaseData('operational');
        $data['payer_id'] = 99999; // non-existent

        $validator = $this->validate($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('payer_id', $validator->errors()->toArray());
    }

    /**
     * التحقق من أن member_ids في shared expense يجب أن تكون موجودة
     * Validates: Requirements 10.5
     */
    public function test_nonexistent_member_id_in_shared_expense_fails_validation(): void
    {
        $data = $this->validBaseData('shared');
        $data['split_type'] = 'equal';
        $data['members']    = [['id' => 99999]]; // non-existent

        $validator = $this->validate($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('members.0.id', $validator->errors()->toArray());
    }

    /**
     * التحقق من أن مجموع التقسيم اليدوي يجب أن يساوي المبلغ الإجمالي
     * Validates: Requirements 2.5, 2.6
     */
    public function test_manual_split_sum_mismatch_fails_validation(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();

        $data = $this->validBaseData('shared');
        $data['amount']     = 100.00;
        $data['split_type'] = 'manual';
        $data['members']    = [
            ['id' => $member1->id, 'amount' => 40.00],
            ['id' => $member2->id, 'amount' => 40.00], // sum = 80, not 100
        ];

        $request = new StoreExpenseRequest();
        $request->merge($data);
        $validator = Validator::make($data, $request->rules(), $request->messages());
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('members', $validator->errors()->toArray());
    }

    /**
     * التحقق من أن مجموع التقسيم اليدوي الصحيح يجتاز التحقق
     * Validates: Requirements 2.4, 2.5
     */
    public function test_manual_split_with_correct_sum_passes_validation(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();

        $data = $this->validBaseData('shared');
        $data['amount']     = 100.00;
        $data['split_type'] = 'manual';
        $data['members']    = [
            ['id' => $member1->id, 'amount' => 60.00],
            ['id' => $member2->id, 'amount' => 40.00], // sum = 100
        ];

        $request = new StoreExpenseRequest();
        $request->merge($data);
        $validator = Validator::make($data, $request->rules(), $request->messages());
        $request->withValidator($validator);

        $this->assertFalse(
            $validator->fails(),
            'Manual split with correct sum should pass. Errors: '
            . json_encode($validator->errors()->toArray())
        );
    }
}
