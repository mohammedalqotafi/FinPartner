<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMemberWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'نص الرسالة مطلوب',
            'message.min' => 'الرسالة قصيرة جداً',
            'message.max' => 'الرسالة طويلة جداً',
        ];
    }
}