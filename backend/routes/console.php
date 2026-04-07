<?php

use App\Services\WhatsAppService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('whatsapp:test {phone} {message?}', function (WhatsAppService $whatsAppService) {
    $phone = (string) $this->argument('phone');
    $message = (string) ($this->argument('message') ?: 'FinPartner WhatsApp test message');

    $response = $whatsAppService->sendTextMessage($phone, $message);

    $this->info('WhatsApp message sent successfully.');
    $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Send a test WhatsApp message through Meta Cloud API');
