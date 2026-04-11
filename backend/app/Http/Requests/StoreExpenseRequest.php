<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreExpenseRequest - التحقق من بيانات إنشاء المصروف
 *
 * يتحقق من جميع الحقول المطلوبة لإنشاء مصروف جديد بناءً على نوعه.
 * Requirements: 10.1, 10.2, 10.3, 10.4, 10.5
 */
class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق الأساسية لجميع أنواع المصروفات
     * Requirements: 10.1, 10.2
     */
    public function rules(): array
    {
        $expenseType = $this->input('expense_type');

        $rules = [
            // المبلغ: يجب أن يكون أكبر من 0.01 - Requirements: 10.1
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],

            // نوع المصروف: أحد الأنواع الثلاثة المسموحة - Requirements: 10.2
            'expense_type' => ['required', 'string', Rule::in(['shared', 'operational', 'personal'])],

            // التصنيف: إلزامي - Requirements: 6.1
            'category' => ['required', 'string', 'max:255'],

            // طريقة الدفع: إلزامية - Requirements: 7.1, 7.2
            'payment_method' => ['required', 'string', Rule::in(['cash', 'transfer'])],

            // التاريخ والوقت: إلزامي - Requirements: 8.1, 8.2
            'expense_datetime' => ['required', 'date'],

            // الدافع: اختياري (null = الخزانة) - Requirements: 3.1
            'payer_id' => ['nullable', 'integer', 'exists:members,id'],

            // الوصف: اختياري - Requirements: 6.2
            'description' => ['nullable', 'string', 'max:1000'],
        ];

        // قواعد خاصة بنوع المصروف الشخصي - Requirements: 10.3
        if ($expenseType === 'personal') {
            $rules['affected_member_id'] = ['required', 'integer', 'exists:members,id'];
        } else {
            $rules['affected_member_id'] = ['nullable', 'integer', 'exists:members,id'];
        }

        // قواعد خاصة بنوع المصروف المشترك - Requirements: 10.4, 10.5
        if ($expenseType === 'shared') {
            $rules['split_type']         = ['required', 'string', Rule::in(['equal', 'manual'])];
            $rules['members']            = ['required', 'array', 'min:1'];
            $rules['members.*.id']       = ['required', 'integer', 'exists:members,id'];
            $rules['members.*.amount']   = ['nullable', 'numeric', 'min:0.01'];
        } else {
            $rules['split_type'] = ['nullable', 'string', Rule::in(['equal', 'manual'])];
            $rules['members']    = ['nullable', 'array'];
        }

        return $rules;
    }

    /**
     * رسائل الخطأ بالعربية
     */
    public function messages(): array
    {
        return [
            // المبلغ
            'amount.required'      => 'المبلغ مطلوب',
            'amount.numeric'       => 'المبلغ يجب أن يكون رقماً',
            'amount.min'           => 'المبلغ يجب أن يكون أكبر من 0.01',
            'amount.max'           => 'المبلغ تجاوز الحد الأقصى المسموح',

            // نوع المصروف
            'expense_type.required' => 'نوع المصروف مطلوب',
            'expense_type.in'       => 'نوع المصروف يجب أن يكون: shared أو operational أو personal',

            // التصنيف
            'category.required' => 'تصنيف المصروف مطلوب',
            'category.max'      => 'التصنيف لا يجب أن يتجاوز 255 حرفاً',

            // طريقة الدفع
            'payment_method.required' => 'طريقة الدفع مطلوبة',
            'payment_method.in'       => 'طريقة الدفع يجب أن تكون: cash أو transfer',

            // التاريخ والوقت
            'expense_datetime.required' => 'تاريخ ووقت المصروف مطلوب',
            'expense_datetime.date'     => 'صيغة التاريخ والوقت غير صحيحة',

            // الدافع
            'payer_id.integer' => 'معرف الدافع يجب أن يكون رقماً صحيحاً',
            'payer_id.exists'  => 'العضو الدافع غير موجود',

            // العضو المتأثر (للمصروفات الشخصية)
            'affected_member_id.required' => 'يجب تحديد العضو المتأثر للمصروفات الشخصية',
            'affected_member_id.integer'  => 'معرف العضو المتأثر يجب أن يكون رقماً صحيحاً',
            'affected_member_id.exists'   => 'العضو المتأثر غير موجود',

            // نوع التقسيم (للمصروفات المشتركة)
            'split_type.required' => 'نوع التقسيم مطلوب للمصروفات المشتركة',
            'split_type.in'       => 'نوع التقسيم يجب أن يكون: equal أو manual',

            // قائمة الأعضاء (للمصروفات المشتركة)
            'members.required'    => 'قائمة الأعضاء مطلوبة للمصروفات المشتركة',
            'members.array'       => 'قائمة الأعضاء يجب أن تكون مصفوفة',
            'members.min'         => 'يجب تحديد عضو واحد على الأقل',
            'members.*.id.required' => 'معرف العضو مطلوب',
            'members.*.id.exists'   => 'أحد الأعضاء المحددين غير موجود',
            'members.*.amount.min'  => 'مبلغ حصة العضو يجب أن يكون أكبر من 0.01',
        ];
    }

    /**
     * تجهيز البيانات قبل التحقق
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => $this->amount !== null ? abs((float) $this->amount) : null,
        ]);
    }

    /**
     * التحقق الإضافي بعد التحقق الأساسي
     * يتحقق من أن مجموع التقسيم اليدوي يساوي المبلغ الإجمالي
     * Requirements: 2.5, 2.6
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('expense_type') === 'shared'
                && $this->input('split_type') === 'manual'
                && is_array($this->input('members'))
            ) {
                $totalAmount  = (float) $this->input('amount');
                $membersInput = $this->input('members');

                $splitSum = array_sum(array_column($membersInput, 'amount'));
                $splitSum = round($splitSum, 2);
                $totalAmount = round($totalAmount, 2);

                if (abs($splitSum - $totalAmount) > 0.001) {
                    $validator->errors()->add(
                        'members',
                        "مجموع التقسيم اليدوي ({$splitSum}) لا يساوي المبلغ الإجمالي ({$totalAmount})"
                    );
                }
            }
        });
    }
}
