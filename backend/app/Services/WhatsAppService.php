<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    public function sendTextMessage(string $to, string $message): array
    {
        $phoneNumberId = config('whatsapp.phone_number_id');
        $token = config('whatsapp.token');
        $apiVersion = config('whatsapp.api_version', 'v22.0');

        if (!$phoneNumberId || !$token) {
            throw new \RuntimeException('WhatsApp configuration is incomplete.');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhoneNumber($to),
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", $payload);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->json();
    }

    public function normalizePhoneNumber(string $phoneNumber): string
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?? '';

        if ($digits === '') {
            throw new \InvalidArgumentException('Phone number is empty.');
        }

        $countryCode = (string) config('whatsapp.default_country_code', '967');

        if (str_starts_with($digits, $countryCode)) {
            return $digits;
        }

        if (str_starts_with($digits, '00')) {
            return ltrim($digits, '0');
        }

        if (str_starts_with($digits, '0')) {
            return $countryCode . ltrim($digits, '0');
        }

        return $digits;
    }
}