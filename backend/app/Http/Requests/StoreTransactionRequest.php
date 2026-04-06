<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
             * member_id: يجب أن يكون موجوداً في جدول members وغير محذوف
             */
            'member_id' => ['required', 'integer', 'exists:members,id'],

            /*
             * type: أحد أنواع العمليات الأربعة المدعومة
             */
            'type' => ['required', 'string', 'in:deposit,withdraw,transfer,adjustment'],

            /*
             * amount: مبلغ موجب أكبر من صفر
             * max:999999999.99 لمنع أي إدخالات مشبوهة
             * الاتجاه يُحدَّد بواسطة type
             */
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],

            /*
             * transaction_at: التاريخ والوقت الفعلي للعملية
             * لا يُقبل وقت مستقبلي بعيد (أكثر من يوم)
             */
            'transaction_at' => [
                'nullable',
                'date',
                'before_or_equal:' . now()->addDay()->toDateTimeString(),
            ],

            'note'   => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'in:completed,pending'],
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required'        => 'يجب تحديد العضو',
            'member_id.exists'          => 'العضو المحدد غير موجود',
            'type.required'             => 'يجب تحديد نوع العملية',
            'type.in'                   => 'نوع العملية يجب أن يكون: إيداع، سحب، تحويل، أو تسوية',
            'amount.required'           => 'المبلغ مطلوب',
            'amount.min'                => 'المبلغ يجب أن يكون أكبر من صفر',
            'amount.max'                => 'المبلغ تجاوز الحد الأقصى المسموح',
            'amount.numeric'            => 'المبلغ يجب أن يكون رقماً',
            'transaction_at.before_or_equal' => 'لا يمكن تسجيل عملية بتاريخ مستقبلي',
            'status.in'                 => 'الحالة يجب أن تكون: مكتمل أو معلق',
            'note.max'                  => 'الملاحظة لا يجب أن تتجاوز 500 حرف',
        ];
    }

    protected function prepareForValidation(): void
    {
        $parsedDate = $this->transaction_at 
            ? \Carbon\Carbon::parse($this->transaction_at)->toDateTimeString()
            : now()->toDateTimeString();

        $this->merge([
            'transaction_at' => $parsedDate,
            'status'         => $this->status ?? 'completed',
            'amount'         => abs((float) $this->amount), // نضمن أن المبلغ موجب دائماً
        ]);
    }
}
