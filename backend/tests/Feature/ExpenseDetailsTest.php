<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseDetailsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_expense_details_without_analysis_when_no_current_user_id()
    {
        // Arrange: إنشاء مصروف تشاركي
        $payer = Member::factory()->create(['name' => 'أحمد']);
        $member1 = Member::factory()->create(['name' => 'محمد']);
        $member2 = Member::factory()->create(['name' => 'علي']);

        $expense = Expense::factory()->create([
            'expense_type' => 'shared',
            'amount' => 1000,
            'payer_id' => $payer->id,
            'category' => 'عشاء',
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $payer->id,
            'amount' => 250,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $member1->id,
            'amount' => 250,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $member2->id,
            'amount' => 500,
        ]);

        // Act: طلب التفاصيل بدون current_user_id
        $response = $this->getJson("/api/expenses/{$expense->id}");

        // Assert: يجب أن يرجع الشكل القديم (للتوافق مع الاختبارات الموجودة)
        $response->assertOk();
        $response->assertJsonStructure([
            'id',
            'reference',
            'expense_type',
            'category',
            'amount',
            'payer',
            'splits',
        ]);

        // يجب ألا يحتوي على analysis
        $response->assertJsonMissing(['analysis']);
    }

    /** @test */
    public function it_returns_analysis_for_payer_when_current_user_id_is_provided()
    {
        // Arrange: إنشاء مصروف تشاركي
        $payer = Member::factory()->create(['name' => 'أحمد']);
        $member1 = Member::factory()->create(['name' => 'محمد']);
        $member2 = Member::factory()->create(['name' => 'علي']);

        $expense = Expense::factory()->create([
            'expense_type' => 'shared',
            'amount' => 1000,
            'payer_id' => $payer->id,
            'category' => 'عشاء',
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $payer->id,
            'amount' => 250,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $member1->id,
            'amount' => 250,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $member2->id,
            'amount' => 500,
        ]);

        // Act: طلب التفاصيل مع current_user_id = payer
        $response = $this->getJson("/api/expenses/{$expense->id}?current_user_id={$payer->id}");

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'expense',
            'splits',
            'analysis' => [
                'is_payer',
                'your_share',
                'you_paid',
                'others_owe_you',
                'net_position',
            ],
        ]);

        $analysis = $response->json('analysis');
        $this->assertTrue($analysis['is_payer']);
        $this->assertEquals(250, $analysis['your_share']);
        $this->assertEquals(1000, $analysis['you_paid']);
        $this->assertEquals(750, $analysis['others_owe_you']); // 1000 - 250
        $this->assertEquals(750, $analysis['net_position']);
    }

    /** @test */
    public function it_returns_analysis_for_participant_when_current_user_id_is_provided()
    {
        // Arrange: إنشاء مصروف تشاركي
        $payer = Member::factory()->create(['name' => 'أحمد']);
        $member1 = Member::factory()->create(['name' => 'محمد']);
        $member2 = Member::factory()->create(['name' => 'علي']);

        $expense = Expense::factory()->create([
            'expense_type' => 'shared',
            'amount' => 1000,
            'payer_id' => $payer->id,
            'category' => 'عشاء',
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $payer->id,
            'amount' => 250,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $member1->id,
            'amount' => 250,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $member2->id,
            'amount' => 500,
        ]);

        // Act: طلب التفاصيل مع current_user_id = member1 (مشارك فقط)
        $response = $this->getJson("/api/expenses/{$expense->id}?current_user_id={$member1->id}");

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'expense',
            'splits',
            'analysis' => [
                'is_payer',
                'you_owe',
                'paid_to',
                'paid_to_id',
                'net_position',
            ],
        ]);

        $analysis = $response->json('analysis');
        $this->assertFalse($analysis['is_payer']);
        $this->assertEquals(250, $analysis['you_owe']);
        $this->assertEquals('أحمد', $analysis['paid_to']);
        $this->assertEquals($payer->id, $analysis['paid_to_id']);
        $this->assertEquals(-250, $analysis['net_position']); // سالب = عليه
    }

    /** @test */
    public function it_handles_user_with_larger_share_correctly()
    {
        // Arrange: إنشاء مصروف تشاركي مع حصة أكبر لأحد الأعضاء
        $payer = Member::factory()->create(['name' => 'أحمد']);
        $member1 = Member::factory()->create(['name' => 'محمد']);
        $member2 = Member::factory()->create(['name' => 'علي']);

        $expense = Expense::factory()->create([
            'expense_type' => 'shared',
            'amount' => 1000,
            'payer_id' => $payer->id,
            'category' => 'عشاء',
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $payer->id,
            'amount' => 250,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $member1->id,
            'amount' => 250,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $member2->id,
            'amount' => 500, // حصة أكبر
        ]);

        // Act: طلب التفاصيل مع current_user_id = member2 (حصة أكبر)
        $response = $this->getJson("/api/expenses/{$expense->id}?current_user_id={$member2->id}");

        // Assert
        $response->assertOk();
        $analysis = $response->json('analysis');
        $this->assertFalse($analysis['is_payer']);
        $this->assertEquals(500, $analysis['you_owe']);
        $this->assertEquals(-500, $analysis['net_position']);
    }

    /** @test */
    public function it_does_not_return_analysis_for_non_shared_expenses()
    {
        // Arrange: إنشاء مصروف تشغيلي (ليس تشاركي)
        $payer = Member::factory()->create(['name' => 'أحمد']);

        $expense = Expense::factory()->create([
            'expense_type' => 'operational',
            'amount' => 500,
            'payer_id' => $payer->id,
            'category' => 'صيانة',
        ]);

        // Act: طلب التفاصيل مع current_user_id
        $response = $this->getJson("/api/expenses/{$expense->id}?current_user_id={$payer->id}");

        // Assert: لا يجب أن يحتوي على analysis لأنه ليس مصروف تشاركي
        $response->assertOk();
        $response->assertJsonMissing(['analysis']);
    }

    /** @test */
    public function it_returns_zero_share_when_user_not_in_splits()
    {
        // Arrange: إنشاء مصروف تشاركي
        $payer = Member::factory()->create(['name' => 'أحمد']);
        $member1 = Member::factory()->create(['name' => 'محمد']);
        $outsider = Member::factory()->create(['name' => 'خالد']); // ليس في التقسيمات

        $expense = Expense::factory()->create([
            'expense_type' => 'shared',
            'amount' => 1000,
            'payer_id' => $payer->id,
            'category' => 'عشاء',
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $payer->id,
            'amount' => 500,
        ]);

        ExpenseSplit::factory()->create([
            'expense_id' => $expense->id,
            'member_id' => $member1->id,
            'amount' => 500,
        ]);

        // Act: طلب التفاصيل مع current_user_id = outsider (ليس في التقسيمات)
        $response = $this->getJson("/api/expenses/{$expense->id}?current_user_id={$outsider->id}");

        // Assert
        $response->assertOk();
        $analysis = $response->json('analysis');
        $this->assertFalse($analysis['is_payer']);
        $this->assertEquals(0, $analysis['you_owe']);
        $this->assertEquals(0, $analysis['net_position']);
    }
}
