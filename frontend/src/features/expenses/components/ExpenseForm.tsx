import React, { useState, useMemo, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { X, CheckCircle2, AlertCircle, Users, SplitSquareHorizontal } from 'lucide-react';
import { useAppStore } from '../../../store/useAppStore';
import { expensesService, ApiError } from '../../../services/expenses.service';
import type { ExpenseFormData, ExpenseType, SplitType } from '../../../types/expenses.types';

// ─── Penny Routing (mirrors backend SHA-256 determinism for preview) ──────────
function quickHash(str: string): number {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = (hash << 5) - hash + str.charCodeAt(i);
    hash |= 0;
  }
  return Math.abs(hash);
}

// ─── Zod Schema (17.1: per-field validation with clear Arabic messages) ───────
const expenseSchema = z
  .object({
    reference: z.string().min(1, 'الرقم المرجعي مطلوب'),
    expense_type: z.enum(['shared', 'operational', 'personal'], {
      errorMap: () => ({ message: 'نوع المصروف غير صحيح' }),
    }),
    category: z.string().min(1, 'التصنيف مطلوب'),
    amount: z
      .number({ invalid_type_error: 'المبلغ يجب أن يكون رقماً' })
      .min(0.01, 'المبلغ يجب أن يكون أكبر من 0.01'),
    payment_method: z.enum(['cash', 'transfer'], {
      errorMap: () => ({ message: 'طريقة الدفع مطلوبة' }),
    }),
    description: z.string().optional(),
    expense_datetime: z.string().min(1, 'التاريخ والوقت مطلوبان'),
    affected_member_id: z.number().nullable().optional(),
    payer_id: z.number().nullable().optional(),
    split_type: z.enum(['equal', 'manual']).optional(),
    members: z
      .array(
        z.object({
          id: z.union([z.string(), z.number()]),
          amount: z.number().optional(),
        })
      )
      .optional(),
  })
  .superRefine((data, ctx) => {
    if (data.expense_type === 'personal' && !data.affected_member_id) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: 'يجب تحديد العضو المتأثر للمصروف الشخصي',
        path: ['affected_member_id'],
      });
    }
    if (data.expense_type === 'shared' && (!data.members || data.members.length === 0)) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: 'يجب اختيار عضو واحد على الأقل للمصروف التشاركي',
        path: ['members'],
      });
    }
  });

type FormValues = z.infer<typeof expenseSchema>;

// ─── Helper: field error message ─────────────────────────────────────────────
function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return (
    <p className="mt-1 text-xs text-rose-600 flex items-center gap-1">
      <AlertCircle size={12} />
      {message}
    </p>
  );
}

// ─── ExpenseForm Component ────────────────────────────────────────────────────
export function ExpenseForm({
  onClose,
  onSuccess,
}: {
  onClose: () => void;
  onSuccess?: () => void;
}) {
  const membersList = useAppStore((s) => s.members);
  const [selectedMembers, setSelectedMembers] = useState<string[]>([]);
  const [manualAmounts, setManualAmounts] = useState<Record<string, number>>({});
  // 17.4: backend error state
  const [backendError, setBackendError] = useState<string | null>(null);
  const [backendFieldErrors, setBackendFieldErrors] = useState<Record<string, string[]>>({});

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(expenseSchema as any),
    defaultValues: {
      reference: `EXP-${Date.now().toString().slice(-4)}`,
      expense_type: 'shared',
      category: '',
      payment_method: 'cash',
      expense_datetime: new Date().toISOString().slice(0, 16),
      split_type: 'equal',
      amount: undefined,
      payer_id: null,
      affected_member_id: null,
    },
  });

  const expenseType = watch('expense_type') as ExpenseType;
  const splitType = watch('split_type') as SplitType;
  const totalAmount = watch('amount') || 0;
  const reference = watch('reference');

  // ─── 17.3: Live preview splits (Penny Routing) ───────────────────────────
  const previewSplits = useMemo(() => {
    if (expenseType !== 'shared' || selectedMembers.length === 0) return [];

    if (splitType === 'equal') {
      const baseAmount = Math.floor((totalAmount / selectedMembers.length) * 100) / 100;
      const totalAssigned = baseAmount * selectedMembers.length;
      const remainderCents = Math.round((totalAmount - totalAssigned) * 100);
      const hashInt = quickHash(reference || '');
      const recipientIndex = hashInt % selectedMembers.length;

      return selectedMembers.map((id, index) => {
        const amt = index === recipientIndex ? baseAmount + remainderCents / 100 : baseAmount;
        return { id, amount: Number(amt.toFixed(2)) };
      });
    } else {
      return selectedMembers.map((id) => ({ id, amount: manualAmounts[id] || 0 }));
    }
  }, [expenseType, splitType, totalAmount, selectedMembers, reference, manualAmounts]);

  const splitSum = useMemo(
    () => previewSplits.reduce((s, p) => s + p.amount, 0),
    [previewSplits]
  );

  const isMathVerified =
    totalAmount > 0 && Number(splitSum.toFixed(2)) === Number(totalAmount.toFixed(2));

  // Keep form members field in sync with preview
  useEffect(() => {
    setValue('members', previewSplits);
  }, [previewSplits, setValue]);

  const toggleMember = (id: string) => {
    setSelectedMembers((prev) =>
      prev.includes(id) ? prev.filter((m) => m !== id) : [...prev, id]
    );
  };

  // ─── Submit ───────────────────────────────────────────────────────────────
  const onSubmit = async (data: FormValues) => {
    setBackendError(null);
    setBackendFieldErrors({});

    if (data.expense_type === 'shared' && !isMathVerified) {
      setBackendError('مجموع التقسيمات لا يطابق إجمالي المصروف. يرجى مراجعة التوزيع.');
      return;
    }

    const payload: ExpenseFormData = {
      ...data,
      payer_id: data.payer_id || null,
      affected_member_id: data.affected_member_id || null,
    };

    try {
      await expensesService.create(payload);
      onSuccess?.();
      onClose();
    } catch (err: unknown) {
      // 17.4: Parse backend errors (ApiError with field-level errors)
      if (err instanceof ApiError) {
        if (err.errors && Object.keys(err.errors).length > 0) {
          setBackendFieldErrors(err.errors as Record<string, string[]>);
          setBackendError('يرجى تصحيح الأخطاء أدناه.');
        } else {
          setBackendError(err.message || 'حدث خطأ أثناء حفظ المصروف. يرجى المحاولة مرة أخرى.');
        }
      } else {
        setBackendError('حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.');
      }
    }
  };

  return (
    <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-3xl overflow-hidden max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex justify-between items-center p-6 border-b border-slate-100 bg-slate-50/50">
          <div>
            <h2 className="text-xl font-bold text-slate-900">إضافة مصروف</h2>
            <p className="text-xs text-slate-400 mt-0.5">Unified Ledger — جميع الحقول المطلوبة مُعلَّمة</p>
          </div>
          <button
            onClick={onClose}
            className="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 cursor-pointer"
            aria-label="إغلاق"
          >
            <X size={20} />
          </button>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="p-6 overflow-y-auto flex-1 space-y-5">
          {/* 17.4: Global backend error banner */}
          {backendError && (
            <div
              role="alert"
              className="p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm flex gap-2 items-start"
            >
              <AlertCircle size={16} className="mt-0.5 shrink-0" />
              <div>
                <p className="font-semibold">{backendError}</p>
                {Object.entries(backendFieldErrors).map(([field, msgs]) => (
                  <p key={field} className="text-xs mt-0.5">
                    {field}: {msgs.join(', ')}
                  </p>
                ))}
              </div>
            </div>
          )}

          {/* Row 1: Type + Reference */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label htmlFor="expense_type" className="block text-sm font-semibold text-slate-700 mb-1">
                نوع المصروف <span className="text-rose-500">*</span>
              </label>
              <select
                id="expense_type"
                {...register('expense_type')}
                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 outline-none"
              >
                <option value="shared">تشاركي (Shared)</option>
                <option value="operational">تشغيلي عام (Operational)</option>
                <option value="personal">شخصي (Personal)</option>
              </select>
              <FieldError message={errors.expense_type?.message} />
            </div>

            <div>
              <label htmlFor="reference" className="block text-sm font-semibold text-slate-700 mb-1">
                الرقم المرجعي <span className="text-rose-500">*</span>
              </label>
              <input
                id="reference"
                type="text"
                {...register('reference')}
                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 outline-none"
              />
              <FieldError message={errors.reference?.message} />
            </div>
          </div>

          {/* Row 2: Amount + Payer */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label htmlFor="amount" className="block text-sm font-semibold text-slate-700 mb-1">
                إجمالي المبلغ (ر.س) <span className="text-rose-500">*</span>
              </label>
              <input
                id="amount"
                type="number"
                step="0.01"
                min="0.01"
                placeholder="0.00"
                {...register('amount', { valueAsNumber: true })}
                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold text-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 outline-none"
              />
              <FieldError message={errors.amount?.message} />
            </div>

            <div>
              <label htmlFor="payer_id" className="block text-sm font-semibold text-slate-700 mb-1">الدافع (Payer)</label>
              <select
                id="payer_id"
                {...register('payer_id', { setValueAs: (v) => (v ? Number(v) : null) })}
                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 outline-none"
              >
                <option value="">— خزانة النظام —</option>
                {membersList.map((m) => (
                  <option key={m.id} value={m.id}>
                    {m.name}
                  </option>
                ))}
              </select>
              <p className="text-[10px] text-slate-400 mt-1">
                يُضاف تلقائياً لحساب الدافع كرصيد إيجابي.
              </p>
            </div>
          </div>

          {/* Personal: affected member */}
          {expenseType === 'personal' && (
            <div>
              <label htmlFor="affected_member_id" className="block text-sm font-semibold text-rose-700 mb-1">
                العضو المتأثر <span className="text-rose-500">*</span>
              </label>
              <select
                id="affected_member_id"
                {...register('affected_member_id', { valueAsNumber: true })}
                className="w-full bg-rose-50 border border-rose-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-rose-500 focus:border-rose-400 outline-none"
              >
                <option value="">— اختر العضو —</option>
                {membersList.map((m) => (
                  <option key={m.id} value={m.id}>
                    {m.name}
                  </option>
                ))}
              </select>
              <FieldError message={errors.affected_member_id?.message} />
            </div>
          )}

          {/* Row 3: Category + Description */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label htmlFor="category" className="block text-sm font-semibold text-slate-700 mb-1">
                التصنيف <span className="text-rose-500">*</span>
              </label>
              <input
                id="category"
                type="text"
                placeholder="غيارات، رواتب، إيجار..."
                {...register('category')}
                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 outline-none"
              />
              <FieldError message={errors.category?.message} />
            </div>

            <div>
              <label htmlFor="description" className="block text-sm font-semibold text-slate-700 mb-1">البيان (اختياري)</label>
              <input
                id="description"
                type="text"
                placeholder="وصف إضافي..."
                {...register('description')}
                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 outline-none"
              />
            </div>
          </div>

          {/* Row 4: Date + Payment Method */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label htmlFor="expense_datetime" className="block text-sm font-semibold text-slate-700 mb-1">
                تاريخ ووقت المعاملة <span className="text-rose-500">*</span>
              </label>
              <input
                id="expense_datetime"
                type="datetime-local"
                {...register('expense_datetime')}
                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 outline-none"
              />
              <FieldError message={errors.expense_datetime?.message} />
            </div>

            <div>
              <label htmlFor="payment_method" className="block text-sm font-semibold text-slate-700 mb-1">
                طريقة الدفع <span className="text-rose-500">*</span>
              </label>
              <select
                id="payment_method"
                {...register('payment_method')}
                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 outline-none"
              >
                <option value="cash">نقداً (Cash)</option>
                <option value="transfer">تحويل بنكي</option>
              </select>
              <FieldError message={errors.payment_method?.message} />
            </div>
          </div>

          {/* ─── 17.2 + 17.3: Shared Split UI ─────────────────────────────── */}
          {expenseType === 'shared' && (
            <div className="p-5 bg-indigo-50/60 rounded-2xl border border-indigo-100 space-y-4">
              {/* Header + split type toggle */}
              <div className="flex justify-between items-center">
                <div className="flex items-center gap-2">
                  <SplitSquareHorizontal size={18} className="text-indigo-600" />
                  <h3 className="font-bold text-indigo-900 text-sm">تقسيم المصروف التشاركي</h3>
                </div>
                <div className="flex bg-white rounded-lg p-1 shadow-sm border border-slate-200">
                  <button
                    type="button"
                    onClick={() => setValue('split_type', 'equal')}
                    className={`px-3 py-1.5 text-xs font-bold rounded-md transition-colors cursor-pointer ${
                      splitType === 'equal'
                        ? 'bg-indigo-600 text-white shadow-sm'
                        : 'text-slate-500 hover:text-slate-700'
                    }`}
                  >
                    بالتساوي
                  </button>
                  <button
                    type="button"
                    onClick={() => setValue('split_type', 'manual')}
                    className={`px-3 py-1.5 text-xs font-bold rounded-md transition-colors cursor-pointer ${
                      splitType === 'manual'
                        ? 'bg-indigo-600 text-white shadow-sm'
                        : 'text-slate-500 hover:text-slate-700'
                    }`}
                  >
                    يدوي
                  </button>
                </div>
              </div>

              {/* Member selection */}
              <div>
                <div className="flex items-center gap-1.5 mb-2">
                  <Users size={13} className="text-slate-400" />
                  <label className="text-xs font-semibold text-slate-500">
                    اختر الأعضاء المشاركين:
                  </label>
                </div>
                <div className="flex flex-wrap gap-2">
                  {membersList.map((m) => (
                    <button
                      type="button"
                      key={m.id}
                      onClick={() => toggleMember(m.id)}
                      className={`px-3 py-1.5 text-sm rounded-full transition-all font-medium border cursor-pointer ${
                        selectedMembers.includes(m.id)
                          ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
                          : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-300 hover:text-indigo-600'
                      }`}
                    >
                      {m.name}
                    </button>
                  ))}
                </div>
                <FieldError message={errors.members?.message} />
              </div>

              {/* 17.3: Live split preview */}
              {selectedMembers.length > 0 && (
                <div className="bg-white rounded-xl border border-indigo-100 shadow-sm overflow-hidden">
                  <div className="px-4 py-2 bg-indigo-50 border-b border-indigo-100">
                    <p className="text-xs font-semibold text-indigo-700">
                      معاينة التوزيع — {selectedMembers.length} أعضاء
                    </p>
                  </div>
                  <div className="divide-y divide-slate-50">
                    {selectedMembers.map((id) => {
                      const member = membersList.find((m) => String(m.id) === String(id));
                      const splitAmt =
                        previewSplits.find((s) => String(s.id) === String(id))?.amount || 0;
                      return (
                        <div key={id} className="flex justify-between items-center px-4 py-2.5">
                          <span className="text-sm font-semibold text-slate-700">
                            {member?.name}
                          </span>
                          {splitType === 'equal' ? (
                            <span className="font-mono text-sm text-indigo-600 font-bold bg-indigo-50 px-3 py-1 rounded-lg">
                              {splitAmt.toFixed(2)} ر.س
                            </span>
                          ) : (
                            <input
                              type="number"
                              step="0.01"
                              min="0"
                              placeholder="0.00"
                              className="w-28 px-2 py-1 border border-slate-200 rounded-lg text-sm text-center font-mono focus:ring-2 focus:ring-indigo-400 outline-none"
                              value={manualAmounts[id] || ''}
                              onChange={(e) =>
                                setManualAmounts((p) => ({
                                  ...p,
                                  [id]: parseFloat(e.target.value) || 0,
                                }))
                              }
                            />
                          )}
                        </div>
                      );
                    })}
                  </div>
                </div>
              )}

              {/* 17.3: Math verification badge */}
              <div className="flex items-center justify-between pt-1">
                <div>
                  {splitType === 'manual' && selectedMembers.length > 0 && (
                    <span className="text-sm font-bold text-slate-600">
                      المجموع:{' '}
                      <span
                        className={
                          isMathVerified ? 'text-emerald-600' : 'text-rose-600'
                        }
                      >
                        {splitSum.toFixed(2)}
                      </span>{' '}
                      / {totalAmount.toFixed(2)} ر.س
                    </span>
                  )}
                </div>
                {selectedMembers.length > 0 && (
                  <>
                    {isMathVerified ? (
                      <div
                        role="status"
                        aria-label="split-verified"
                        className="flex items-center gap-1.5 text-emerald-600 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-full text-xs font-extrabold"
                      >
                        <CheckCircle2 size={14} />
                        Split Verified ✔
                      </div>
                    ) : (
                      <div
                        role="status"
                        aria-label="split-unverified"
                        className="flex items-center gap-1.5 text-rose-600 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-full text-xs font-bold"
                      >
                        <AlertCircle size={14} />
                        التوزيع لا يتطابق
                      </div>
                    )}
                  </>
                )}
              </div>
            </div>
          )}
        </form>

        {/* Footer */}
        <div className="p-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3 rounded-b-2xl">
          <button
            type="button"
            onClick={onClose}
            className="px-5 py-2 text-slate-600 font-bold hover:bg-slate-200 rounded-xl transition cursor-pointer"
          >
            إلغاء
          </button>
          <button
            type="button"
            onClick={handleSubmit(onSubmit)}
            disabled={
              isSubmitting || (expenseType === 'shared' && selectedMembers.length > 0 && !isMathVerified)
            }
            className="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold rounded-xl shadow-lg transition cursor-pointer disabled:opacity-50 flex items-center gap-2"
          >
            <CheckCircle2 size={18} />
            {isSubmitting ? 'جاري الحفظ...' : 'اعتماد وحفظ المصروف'}
          </button>
        </div>
      </div>
    </div>
  );
}
