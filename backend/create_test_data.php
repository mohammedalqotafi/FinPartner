<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Member;
use App\Models\Expense;
use App\Models\ExpenseSplit;

echo "Creating test data for 'Who Owes Who' feature...\n\n";

// Get members
$members = Member::all();

if ($members->count() < 2) {
    echo "❌ Need at least 2 members. Please create members first.\n";
    exit(1);
}

echo "Found {$members->count()} members:\n";
foreach ($members as $member) {
    echo "  - {$member->name} (ID: {$member->id})\n";
}
echo "\n";

// Create a shared expense
$payer = $members->first();
$totalAmount = 1000;
$splitAmount = $totalAmount / $members->count();

echo "Creating shared expense:\n";
echo "  Payer: {$payer->name}\n";
echo "  Total: {$totalAmount} ر.س\n";
echo "  Split: {$splitAmount} ر.س per member\n\n";

$expense = Expense::create([
    'reference' => 'EXP-TEST-' . time(),
    'expense_type' => 'shared',
    'category' => 'طعام',
    'amount' => $totalAmount,
    'payer_id' => $payer->id,
    'payment_method' => 'cash',
    'expense_datetime' => now(),
    'description' => 'مصروف تجريبي لاختبار "من يدين لمن"',
]);

// Create splits for all members
foreach ($members as $member) {
    ExpenseSplit::create([
        'expense_id' => $expense->id,
        'member_id' => $member->id,
        'amount' => $splitAmount,
    ]);
    echo "  ✓ Split created for {$member->name}: {$splitAmount} ر.س\n";
}

echo "\n✅ Test data created successfully!\n";
echo "\nNow open: http://localhost:5174/members/{$payer->id}/financials\n";
echo "Scroll down to see 'من يدين لمن' section!\n";
