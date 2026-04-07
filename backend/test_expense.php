<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/expenses', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json'
], json_encode([
    'reference' => 'EXP-9999',
    'expense_type' => 'shared',
    'category' => 'عام',
    'amount' => 2000,
    'payment_method' => 'cash',
    'expense_datetime' => '2026-04-07T01:36',
    'split_type' => 'equal',
    'members' => [
        ['id' => 1]
    ]
]));

$response = $kernel->handle($request);
echo "STATUS: " . $response->getStatusCode() . "\n";
echo "CONTENT: " . $response->getContent() . "\n";
