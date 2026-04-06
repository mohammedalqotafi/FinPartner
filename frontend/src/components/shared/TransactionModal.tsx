import { useState } from 'react';
import { X } from 'lucide-react';
import { useAppStore } from '../../store/useAppStore';
import type { TransactionType } from '../../types';
import { TX_TYPE_LABEL } from '../../utils/finance';

interface TransactionModalProps {
  defaultType?: TransactionType;
  defaultMemberId?: string;
  onClose: () => void;
}

export function TransactionModal({
  defaultType = 'deposit',
  defaultMemberId = '',
  onClose,
}: TransactionModalProps) {
  const members = useAppStore((s) => s.members);
  const addTransaction = useAppStore((s) => s.addTransaction);

  const [form, setForm] = useState({
    memberId: defaultMemberId,
    type: defaultType as TransactionType,
    amount: '',
    note: '',
    status: 'completed' as 'completed' | 'pending',
    datetime: new Date().toISOString().slice(0, 16), // datetime-local format
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  const validate = () => {
    const e: Record<string, string> = {};
    if (!form.memberId) e.memberId = 'اختر عضوًا';
    if (!form.amount || Number(form.amount) <= 0) e.amount = 'أدخل مبلغًا صحيحًا';
    return e;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const errs = validate();
    if (Object.keys(errs).length > 0) { setErrors(errs); return; }
    
    try {
      await addTransaction({
      memberId: form.memberId,
      type: form.type,
      amount: Number(form.amount),
      note: form.note,
      status: form.status,
      });
      onClose();
    } catch (err: any) {
      alert("خطأ: " + err.message);
    }
  };

  const TYPE_OPTIONS: { value: TransactionType; color: string }[] = [
    { value: 'deposit',    color: 'border-emerald-500 bg-emerald-50 text-emerald-700' },
    { value: 'withdraw',   color: 'border-rose-500 bg-rose-50 text-rose-700' },
    { value: 'transfer',   color: 'border-blue-500 bg-blue-50 text-blue-700' },
    { value: 'adjustment', color: 'border-slate-400 bg-slate-50 text-slate-600' },
  ];

  const selectedColor = TYPE_OPTIONS.find((o) => o.value === form.type)?.color ?? '';

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        {/* Header */}
        <div className={`px-6 py-4 rounded-t-2xl flex items-center justify-between border-b ${selectedColor}`}>
          <div>
            <h2 className="text-base font-bold text-slate-900">
              عملية جديدة — {TX_TYPE_LABEL[form.type]}
            </h2>
            <p className="text-xs text-slate-500 mt-0.5">سيتم تسجيل العملية في دفتر الأستاذ</p>
          </div>
          <button onClick={onClose} className="w-8 h-8 rounded-lg bg-white/70 hover:bg-white flex items-center justify-center cursor-pointer">
            <X size={16} />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {/* Type selector */}
          <div>
            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">نوع العملية</label>
            <div className="grid grid-cols-4 gap-2">
              {TYPE_OPTIONS.map(({ value, color }) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => setForm({ ...form, type: value })}
                  className={`py-2.5 rounded-xl text-xs font-bold border-2 transition-all cursor-pointer ${
                    form.type === value ? color : 'border-slate-200 text-slate-400 hover:border-slate-300'
                  }`}
                >
                  {TX_TYPE_LABEL[value]}
                </button>
              ))}
            </div>
          </div>

          {/* Member */}
          <div>
            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">العضو</label>
            <select
              value={form.memberId}
              onChange={(e) => setForm({ ...form, memberId: e.target.value })}
              className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="">-- اختر العضو --</option>
              {members.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.name} — الرصيد: {m.balance.toLocaleString('en-US')} ر.س
                </option>
              ))}
            </select>
            {errors.memberId && <p className="text-rose-500 text-xs mt-1">{errors.memberId}</p>}
          </div>

          {/* Amount + DateTime */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">المبلغ (ر.س)</label>
              <input
                type="number"
                placeholder="0.00"
                step="0.01"
                min="0.01"
                value={form.amount}
                onChange={(e) => setForm({ ...form, amount: e.target.value })}
                className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
              {errors.amount && <p className="text-rose-500 text-xs mt-1">{errors.amount}</p>}
            </div>
            <div>
              <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">التاريخ والوقت</label>
              <input
                type="datetime-local"
                value={form.datetime}
                onChange={(e) => setForm({ ...form, datetime: e.target.value })}
                className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>

          {/* Status */}
          <div>
            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">الحالة</label>
            <div className="flex gap-3">
              {(['completed', 'pending'] as const).map((s) => (
                <label
                  key={s}
                  className={`flex-1 flex items-center justify-center py-2.5 rounded-xl border-2 cursor-pointer text-sm font-semibold transition-all ${
                    form.status === s
                      ? s === 'completed'
                        ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                        : 'border-amber-400 bg-amber-50 text-amber-700'
                      : 'border-slate-200 text-slate-400'
                  }`}
                >
                  <input
                    type="radio"
                    name="status"
                    value={s}
                    checked={form.status === s}
                    onChange={() => setForm({ ...form, status: s })}
                    className="hidden"
                  />
                  {s === 'completed' ? '✓ مكتمل' : '⏳ معلق'}
                </label>
              ))}
            </div>
          </div>

          {/* Note */}
          <div>
            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">البيان / الوصف</label>
            <input
              type="text"
              placeholder="وصف العملية..."
              value={form.note}
              onChange={(e) => setForm({ ...form, note: e.target.value })}
              className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div className="flex gap-3 pt-2">
            <button
              type="submit"
              className="flex-1 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition text-sm cursor-pointer"
            >
              تسجيل العملية
            </button>
            <button
              type="button"
              onClick={onClose}
              className="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold rounded-xl text-sm cursor-pointer"
            >
              إلغاء
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
