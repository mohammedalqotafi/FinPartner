<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Single-admin system, no authorization needed
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'min:2', 'max:100'],
            'email'           => ['nullable', 'email', 'max:150', 'unique:members,email'],
            'phone'           => ['nullable', 'string', 'regex:/^[0-9+\-\s]+$/', 'max:20'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'join_date'       => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'            => 'اسم العضو مطلوب',
            'name.min'                 => 'الاسم يجب أن يكون حرفين على الأقل',
            'email.unique'             => 'البريد الإلكتروني مستخدم من قبل',
            'email.email'              => 'صيغة البريد الإلكتروني غير صحيحة',
            'phone.regex'              => 'رقم الجوال يجب أن يحتوي على أرقام فقط',
            'opening_balance.numeric'  => 'الرصيد الافتتاحي يجب أن يكون رقماً',
            'opening_balance.min'      => 'الرصيد الافتتاحي لا يمكن أن يكون سالباً',
            'join_date.before_or_equal'=> 'تاريخ الانضمام لا يمكن أن يكون في المستقبل',
        ];
    }

    /**
     * تجهيز البيانات قبل التحقق
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'opening_balance' => $this->opening_balance ?? 0,
            'join_date'       => $this->join_date ?? now()->toDateString(),
        ]);
    }
}
