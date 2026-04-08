import React, { useState, useEffect } from 'react';
import { Plus, Search, Eye, Filter } from 'lucide-react';
import { expensesService } from '../../services/expenses.service';
import type { Expense } from '../../types/expenses.types';
import { ExpenseForm } from './components/ExpenseForm';
import { ExpenseDetails } from './components/ExpenseDetails';

export function ExpensesPage() {
  const [expenses, setExpenses] = useState<Expense[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [viewExpense, setViewExpense] = useState<Expense | null>(null);
  
  const [search, setSearch] = useState('');
  const [filterType, setFilterType] = useState('all');

  const loadExpenses = async () => {
    try {
      const data = await expensesService.getAll();
      setExpenses(data);
    } catch (e) {
      console.error(e);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    loadExpenses();
  }, []);

  const filtered = expenses.filter(exp => {
    if (filterType !== 'all' && exp.expense_type !== filterType) return false;
    if (search && !exp.reference.includes(search) && !exp.category.includes(search)) return false;
    return true;
  });

  const totalShared = filtered.filter(e => e.expense_type === 'shared').reduce((s, e) => s + parseFloat(e.amount), 0);
  const totalOp = filtered.filter(e => e.expense_type === 'operational').reduce((s, e) => s + parseFloat(e.amount), 0);

  return (
    <div className="p-8 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900">سجل المصروفات</h1>
          <p className="text-slate-500 mt-1 text-sm">دفتر العمليات الموحد (المصاريف التشغيلية، الشخصية، والمشتركة)</p>
        </div>
        <button onClick={() => setShowForm(true)} className="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-200 transition text-sm cursor-pointer">
          <Plus size={18} /> إضافة مصروف
        </button>
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm text-center">
          <p className="text-xs text-slate-500">إجمالي المصروفات المعروضة</p>
          <p className="text-xl font-extrabold text-slate-800 mt-1">{filtered.reduce((s, e) => s + parseFloat(e.amount), 0).toFixed(2)} ر.س</p>
        </div>
        <div className="bg-emerald-50 rounded-2xl p-4 border border-emerald-100 shadow-sm text-center">
          <p className="text-xs text-emerald-600">مصروفات مشتركة (Team Splits)</p>
          <p className="text-xl font-extrabold text-emerald-700 mt-1">{totalShared.toFixed(2)} ر.س</p>
        </div>
        <div className="bg-amber-50 rounded-2xl p-4 border border-amber-100 shadow-sm text-center">
          <p className="text-xs text-amber-600">عمليات تشغيلية (Operational)</p>
          <p className="text-xl font-extrabold text-amber-700 mt-1">{totalOp.toFixed(2)} ر.س</p>
        </div>
      </div>

      <div className="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex flex-wrap gap-3">
        <div className="relative flex-1 min-w-44">
          <Search size={15} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input type="text" placeholder="بحث بالرقم أو التصنيف..." value={search} onChange={(e) => setSearch(e.target.value)} className="w-full pr-9 pl-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div className="flex rounded-xl bg-slate-100 p-1 gap-0.5">
          {['all', 'shared', 'operational', 'personal'].map((t) => (
            <button key={t} onClick={() => setFilterType(t)} className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer ${filterType === t ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-400 hover:text-slate-600'}`}>
              {t === 'all' ? 'الكل' : t}
            </button>
          ))}
        </div>
      </div>

      <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-right min-w-[900px]">
            <thead>
              <tr className="bg-slate-700 text-white">
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">المرجع</th>
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">تاريخ الدفتر</th>
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">النوع</th>
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">القسم</th>
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">المبلغ</th>
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">الدافع (Payer)</th>
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">إجراء</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-50">
              {isLoading ? (
                <tr><td colSpan={7} className="py-16 text-center text-slate-400 font-medium">جاري التحميل...</td></tr>
              ) : filtered.map(exp => (
                <tr key={exp.id} className="hover:bg-slate-50/60 transition-colors">
                  <td className="py-4 px-5"><span className="font-mono text-xs font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded-lg">{exp.reference}</span></td>
                  <td className="py-4 px-5 text-slate-500 text-xs whitespace-nowrap">{new Date(exp.expense_datetime).toLocaleString('ar-SA')}</td>
                  <td className="py-4 px-5">
                    <span className={`px-2.5 py-1 rounded-full text-xs font-semibold ${exp.expense_type === 'shared' ? 'bg-indigo-50 text-indigo-700' : exp.expense_type === 'personal' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'}`}>{exp.expense_type}</span>
                  </td>
                  <td className="py-4 px-5 text-slate-800 text-sm font-medium">{exp.category}</td>
                  <td className="py-4 px-5 font-mono font-extrabold text-sm text-slate-800">{parseFloat(exp.amount).toFixed(2)} ر.س</td>
                  <td className="py-4 px-5 text-sm text-slate-600 font-bold">{exp.payer ? exp.payer.name : '- خزانة -'}</td>
                  <td className="py-4 px-5">
                    <button onClick={() => setViewExpense(exp)} className="p-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg transition cursor-pointer">
                      <Eye size={16} />
                    </button>
                  </td>
                </tr>
              ))}
              {!isLoading && filtered.length === 0 && (
                <tr><td colSpan={7} className="py-16 text-center text-slate-400 font-medium">لا توجد سجلات مطابقة</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {showForm && <ExpenseForm onClose={() => setShowForm(false)} onSuccess={loadExpenses} />}
      {viewExpense && <ExpenseDetails expense={viewExpense} onClose={() => setViewExpense(null)} />}
    </div>
  );
}
