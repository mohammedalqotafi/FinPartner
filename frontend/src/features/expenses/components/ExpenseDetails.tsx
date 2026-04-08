import React from 'react';
import { X, Calendar, User, Tag, CreditCard, ArrowRightLeft } from 'lucide-react';
import type { Expense } from '../../../types/expenses.types';

export function ExpenseDetails({ expense, onClose }: { expense: Expense, onClose: () => void }) {
  const isPayerParticipant = expense.expense_type === 'shared' && 
                             expense.payer_id !== null && 
                             expense.splits?.some(s => s.member_id === expense.payer_id);
  
  const payerSplitAmount = expense.splits?.find(s => s.member_id === expense.payer_id)?.amount || '0';
  const netEffect = parseFloat(expense.amount) - parseFloat(payerSplitAmount);

  return (
    <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col">
        <div className="flex justify-between items-center p-6 border-b border-slate-100 bg-slate-50/50">
          <div>
            <span className="text-xs font-mono font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-md mb-1 block w-fit">{expense.reference}</span>
            <h2 className="text-xl font-bold text-slate-900">تفاصيل المصروف</h2>
          </div>
          <button onClick={onClose} className="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100">
            <X size={20} />
          </button>
        </div>

        <div className="p-6 overflow-y-auto">
          {/* Main Stats */}
          <div className="flex items-center justify-between mb-6 p-4 bg-indigo-600 rounded-2xl text-white shadow-md">
            <div>
              <p className="text-indigo-200 text-xs font-medium mb-1">المبلغ الإجمالي</p>
              <p className="text-3xl font-extrabold">{parseFloat(expense.amount).toFixed(2)} ر.س</p>
            </div>
            <div className="text-left">
              <p className="text-indigo-200 text-xs font-medium mb-1">النوع</p>
              <span className="font-bold text-sm bg-indigo-500/50 px-3 py-1 rounded-full uppercase tracking-wider">{expense.expense_type}</span>
            </div>
          </div>

          <div className="space-y-4">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div className="flex items-center gap-2 text-slate-600"><Tag size={16}/> <span className="text-sm font-semibold">القسم والبيان</span></div>
              <div className="text-left text-sm font-medium text-slate-800">{expense.category} <span className="text-slate-400">({expense.description || 'بدون بيان'})</span></div>
            </div>
            
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div className="flex items-center gap-2 text-slate-600"><Calendar size={16}/> <span className="text-sm font-semibold">التاريخ</span></div>
              <div className="text-left text-sm font-medium text-slate-800">{new Date(expense.expense_datetime).toLocaleString('ar-SA')}</div>
            </div>

            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div className="flex items-center gap-2 text-slate-600"><User size={16}/> <span className="text-sm font-semibold">الجهة الدافعة (Payer)</span></div>
              <div className="text-left text-sm font-bold text-indigo-700 bg-indigo-50 px-3 py-1 rounded-lg">
                {expense.payer ? expense.payer.name : 'خزانة النظام (Company)'}
              </div>
            </div>

            {expense.expense_type === 'personal' && expense.affected_member && (
              <div className="flex items-center justify-between border-b border-rose-100 pb-3 bg-rose-50/50 p-2 rounded-lg">
                <div className="flex items-center gap-2 text-rose-700"><User size={16}/> <span className="text-sm font-bold">العضو المتأثر (Personal)</span></div>
                <div className="text-left text-sm font-bold text-rose-800">{expense.affected_member.name}</div>
              </div>
            )}
          </div>

          {/* Splits Breakdown */}
          {expense.expense_type === 'shared' && expense.splits && expense.splits.length > 0 && (
            <div className="mt-8">
              <h3 className="text-sm font-extrabold text-slate-800 mb-3 flex items-center gap-2">
                <ArrowRightLeft size={16} className="text-indigo-500"/> تفصيل التقسيم (Splits Ledger)
              </h3>
              <div className="border border-slate-200 rounded-xl overflow-hidden">
                {expense.splits.map((split, i) => (
                  <div key={split.id} className={`flex justify-between items-center p-3 text-sm ${i !== expense.splits!.length - 1 ? 'border-b border-slate-100' : ''} ${split.member_id === expense.payer_id ? 'bg-amber-50/40' : 'bg-slate-50/30'}`}>
                    <span className="font-semibold text-slate-700">{split.member?.name || `عضو #${split.member_id}`} {split.member_id === expense.payer_id && <span className="text-[10px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded ml-2 font-bold">(Payer)</span>}</span>
                    <span className="font-mono font-bold text-slate-800">{parseFloat(split.amount).toFixed(2)} ر.س</span>
                  </div>
                ))}
              </div>

              {isPayerParticipant && (
                <div className="mt-4 p-4 bg-emerald-50 rounded-xl border border-emerald-100 shadow-sm">
                  <p className="text-xs font-bold text-emerald-800 mb-1">النتيجة المحاسبية للدافع (Net Effect)</p>
                  <p className="text-sm text-emerald-700">
                    بما أن <strong>{expense.payer!.name}</strong> دفع المبلغ كامل ({parseFloat(expense.amount).toFixed(2)}) وهو مشارك بنسبة ({parseFloat(payerSplitAmount).toFixed(2)})، فإن صافي رصيده سيزداد بمقدار: <span className="font-extrabold text-lg block mt-1">+{netEffect.toFixed(2)} ر.س</span>
                  </p>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
