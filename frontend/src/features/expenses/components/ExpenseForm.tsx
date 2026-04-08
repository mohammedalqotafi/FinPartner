import React, { useState, useMemo, useEffect } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { X, CheckCircle2, AlertCircle } from 'lucide-react';
import { useAppStore } from '../../../store/useAppStore';
import { expensesService } from '../../../services/expenses.service';
import type { ExpenseFormData, ExpenseType, SplitType } from '../../../types/expenses.types';

// Simple deterministic string to int hash for frontend preview
function quickHash(str: string) {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = (hash << 5) - hash + str.charCodeAt(i);
    hash |= 0;
  }
  return Math.abs(hash);
}

const expenseSchema = z.object({
  reference: z.string().min(1, 'المرجع مطلوب'),
  expense_type: z.enum(['shared', 'operational', 'personal']),
  category: z.string().min(1, 'القسم مطلوب'),
  amount: z.number().min(0.01, 'المبلغ غير صحيح'),
  payment_method: z.string().min(1, 'طريقة الدفع مطلوبة'),
  description: z.string().optional(),
  expense_datetime: z.string().min(1, 'التاريخ مطلوب'),
  affected_member_id: z.number().nullable().optional(),
  payer_id: z.number().nullable().optional(),
  split_type: z.enum(['equal', 'manual']).optional(),
  members: z.array(z.object({
    id: z.union([z.string(), z.number()]),
    amount: z.number().optional()
  })).optional()
}).refine(data => {
  if (data.expense_type === 'personal' && !data.affected_member_id) return false;
  if (data.expense_type === 'shared' && (!data.members || data.members.length === 0)) return false;
  return true;
}, {
  message: "بيانات غير مكتملة (يرجى التحقق من العضو المتأثر أو أعضاء التقسيم)",
  path: ["expense_type"]
});

export function ExpenseForm({ onClose, onSuccess }: { onClose: () => void, onSuccess?: () => void }) {
  const membersList = useAppStore((s) => s.members);
  const [selectedMembers, setSelectedMembers] = useState<string[]>([]);
  const [manualAmounts, setManualAmounts] = useState<Record<string, number>>({});

  const { register, handleSubmit, watch, setValue, control, formState: { errors, isSubmitting } } = useForm<ExpenseFormData>({
    resolver: zodResolver(expenseSchema as any),
    defaultValues: {
      reference: `EXP-${Date.now().toString().slice(-4)}`,
      expense_type: 'shared',
      category: 'عام',
      payment_method: 'cash',
      expense_datetime: new Date().toISOString().slice(0, 16),
      split_type: 'equal',
      amount: 0,
      payer_id: null,
      affected_member_id: null
    }
  });

  const expenseType = watch('expense_type');
  const splitType = watch('split_type');
  const totalAmount = watch('amount') || 0;
  const reference = watch('reference');

  // Preview logic matching backend mathematical routing
  const previewSplits = useMemo(() => {
    if (expenseType !== 'shared' || selectedMembers.length === 0) return [];
    
    if (splitType === 'equal') {
      const baseAmount = Math.floor((totalAmount / selectedMembers.length) * 100) / 100;
      const totalAssigned = baseAmount * selectedMembers.length;
      const remainderCents = Math.round((totalAmount - totalAssigned) * 100);
      
      const hashInt = quickHash(reference);
      const recipientIndex = hashInt % selectedMembers.length;

      return selectedMembers.map((id, index) => {
        let amt = baseAmount;
        if (index === recipientIndex) amt += (remainderCents / 100);
        return { id, amount: Number(amt.toFixed(2)) };
      });
    } else {
      return selectedMembers.map(id => ({ id, amount: manualAmounts[id] || 0 }));
    }
  }, [expenseType, splitType, totalAmount, selectedMembers, reference, manualAmounts]);

  const manualSum = useMemo(() => {
    if (splitType !== 'manual') return 0;
    return previewSplits.reduce((sum, s) => sum + (s.amount || 0), 0);
  }, [previewSplits, splitType]);

  const isMathVerified = splitType === 'equal' 
    ? previewSplits.reduce((s,p) => s + p.amount, 0).toFixed(2) === totalAmount.toFixed(2) && totalAmount > 0
    : manualSum.toFixed(2) === totalAmount.toFixed(2) && totalAmount > 0;

  useEffect(() => {
    setValue('members', previewSplits);
  }, [previewSplits, setValue]);

  const toggleMember = (id: string) => {
    setSelectedMembers(prev => prev.includes(id) ? prev.filter(m => m !== id) : [...prev, id]);
  };

  const onSubmit = async (data: ExpenseFormData) => {
    if (data.expense_type === 'shared' && !isMathVerified) {
      alert('مجموع التقسيمات لا يطابق إجمالي المصروف!');
      return;
    }
    
    // Normalize nulls
    if (data.payer_id === 0) data.payer_id = null;
    if (data.affected_member_id === 0) data.affected_member_id = null;

    try {
      await expensesService.create(data);
      onSuccess?.();
      onClose();
    } catch (err: any) {
      alert(`Failed to save expense:\n${err.message || JSON.stringify(err)}`);
      console.error(err);
    }
  };

  return (
    <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-3xl overflow-hidden max-h-[90vh] flex flex-col">
        <div className="flex justify-between items-center p-6 border-b border-slate-100 bg-slate-50/50">
          <div>
            <h2 className="text-xl font-bold text-slate-900">إضافة مصروف (Unified Ledger)</h2>
          </div>
          <button onClick={onClose} className="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 cursor-pointer">
            <X size={20} />
          </button>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="p-6 overflow-y-auto flex-1">
          {errors.expense_type?.message && (
            <div className="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm flex gap-2"><AlertCircle size={16}/> {errors.expense_type.message as string}</div>
          )}

          <div className="grid grid-cols-2 gap-4 mb-6">
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">نوع المصروف</label>
              <select {...register('expense_type')} className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500">
                <option value="shared">تشاركي (Shared)</option>
                <option value="operational">تشغيلي عام (Operational)</option>
                <option value="personal">شخصي (Personal)</option>
              </select>
            </div>
            
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">الرقم المرجعي</label>
              <input type="text" {...register('reference')} className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">إجمالي المبلغ</label>
              <input type="number" step="0.01" {...register('amount', { valueAsNumber: true })} className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold text-indigo-700 focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">الدافع (Payer)</label>
              <select {...register('payer_id', { setValueAs: v => v ? Number(v) : null })} className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500">
                <option value="">-- خزانة النظام --</option>
                {membersList.map(m => <option key={m.id} value={m.id}>{m.name}</option>)}
              </select>
              <p className="text-[10px] text-slate-400 mt-1">يُضاف تلقائياً لحساب الدافع كرصيد إيجابي.</p>
            </div>

            {expenseType === 'personal' && (
              <div className="col-span-2">
                <label className="block text-sm font-semibold text-rose-700 mb-1">العضو المتأثر (Personal Entry)</label>
                <select {...register('affected_member_id', { valueAsNumber: true })} className="w-full bg-rose-50 border border-rose-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-rose-500">
                  <option value="">-- اختر العضو --</option>
                  {membersList.map(m => <option key={m.id} value={m.id}>{m.name}</option>)}
                </select>
              </div>
            )}
            
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">التصنيف والبيان</label>
              <input type="text" placeholder="التصنيف (غيارات، رواتب..)" {...register('category')} className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm mb-2 focus:ring-2 focus:ring-indigo-500" />
              <input type="text" placeholder="البيان الإضافي" {...register('description')} className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">تاريخ ووقت المعاملة</label>
              <input type="datetime-local" {...register('expense_datetime')} className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 mb-2" />
              <select {...register('payment_method')} className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500">
                <option value="cash">نقداً (Cash)</option>
                <option value="transfer">تحويل بنكي</option>
              </select>
            </div>
          </div>

          {/* Shared Split Logic UI */}
          {expenseType === 'shared' && (
            <div className="mt-4 p-5 bg-indigo-50/50 rounded-2xl border border-indigo-100">
              <div className="flex justify-between items-center mb-4">
                <h3 className="font-bold text-indigo-900">تقسيم المصروفات (Shared Logic)</h3>
                <div className="flex bg-white rounded-lg p-1 shadow-sm border border-slate-200">
                  <button type="button" onClick={() => setValue('split_type', 'equal')} className={`px-3 py-1.5 text-xs font-bold rounded-md ${splitType === 'equal' ? 'bg-indigo-600 text-white' : 'text-slate-500'} cursor-pointer`}>بالتساوي</button>
                  <button type="button" onClick={() => setValue('split_type', 'manual')} className={`px-3 py-1.5 text-xs font-bold rounded-md ${splitType === 'manual' ? 'bg-indigo-600 text-white' : 'text-slate-500'} cursor-pointer`}>يدوي</button>
                </div>
              </div>

              <div className="mb-4">
                <label className="block text-xs font-semibold text-slate-500 mb-2">اختر الأعضاء المشاركين:</label>
                <div className="flex flex-wrap gap-2">
                  {membersList.map(m => (
                    <button type="button" key={m.id} onClick={() => toggleMember(m.id)}
                      className={`px-3 py-1.5 text-sm rounded-full transition-colors font-medium border cursor-pointer ${selectedMembers.includes(m.id) ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-300'}`}>
                      {m.name}
                    </button>
                  ))}
                </div>
              </div>

              {selectedMembers.length > 0 && (
                <div className="space-y-2 mt-4 bg-white p-4 rounded-xl border border-indigo-100 shadow-sm">
                  {selectedMembers.map(id => {
                    const member = membersList.find(m => String(m.id) === String(id));
                    const splitAmt = previewSplits.find(s => String(s.id) === String(id))?.amount || 0;
                    return (
                      <div key={id} className="flex justify-between items-center py-1">
                        <span className="text-sm font-semibold text-slate-700">{member?.name}</span>
                        {splitType === 'equal' ? (
                          <span className="font-mono text-sm text-indigo-600 font-bold bg-indigo-50 px-3 py-1 rounded-lg">{splitAmt.toFixed(2)} ر.س</span>
                        ) : (
                          <input type="number" step="0.01" className="w-24 px-2 py-1 border rounded-lg text-sm text-center" 
                            value={manualAmounts[id] || ''} onChange={e => setManualAmounts(p => ({...p, [id]: parseFloat(e.target.value)||0}))} />
                        )}
                      </div>
                    );
                  })}
                </div>
              )}

              {/* Mathematical Verifier Badge */}
              <div className="mt-4 flex items-center justify-between">
                <div>
                  {splitType === 'manual' && <span className="text-sm font-bold text-slate-600">المجموع الحالي: {manualSum.toFixed(2)} / {totalAmount}</span>}
                </div>
                {isMathVerified ? (
                  <div className="flex items-center gap-1.5 text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full text-xs font-extrabold">
                    <CheckCircle2 size={16} /> Split Verified ✔
                  </div>
                ) : (
                  <div className="flex items-center gap-1.5 text-rose-600 bg-rose-50 px-3 py-1.5 rounded-full text-xs font-bold">
                    <AlertCircle size={16} /> التوزيع لا يتطابق
                  </div>
                )}
              </div>
            </div>
          )}
        </form>

        <div className="p-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3 rounded-b-2xl">
          <button type="button" onClick={onClose} className="px-5 py-2 text-slate-600 font-bold hover:bg-slate-200 rounded-xl transition cursor-pointer">إلغاء</button>
          <button type="button" onClick={handleSubmit(onSubmit)} disabled={isSubmitting || (expenseType === 'shared' && !isMathVerified)} 
            className="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold rounded-xl shadow-lg transition cursor-pointer disabled:opacity-50 flex items-center gap-2">
            <CheckCircle2 size={18} />
            {isSubmitting ? 'جاري الحفظ...' : 'اعتماد وحفظ المصروف'}
          </button>
        </div>
      </div>
    </div>
  );
}
