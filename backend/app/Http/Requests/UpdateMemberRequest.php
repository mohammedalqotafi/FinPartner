<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $memberId = $this->route('member');

        return [
            'name'  => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150',
                        Rule::unique('members', 'email')->ignore($memberId)],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9+\-\s]+$/', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'اسم العضو مطلوب',
            'email.unique'   => 'البريد الإلكتروني مستخدم من قبل',
            'phone.regex'    => 'رقم الجوال يجب أن يحتوي على أرقام فقط',
        ];
    }
}
