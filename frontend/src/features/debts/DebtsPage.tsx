import React, { useState, useEffect } from 'react';
import { Users, TrendingUp, TrendingDown, DollarSign, RefreshCw } from 'lucide-react';
import { debtsService } from '../../services/debts.service';
import type { DebtResponse } from '../../types/debts.types';

export function DebtsPage() {
  const [debtsData, setDebtsData] = useState<DebtResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadDebts = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await debtsService.getAll();
      setDebtsData(data);
    } catch (e) {
      console.error(e);
      setError('حدث خطأ أثناء تحميل الديون');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    loadDebts();
  }, []);

  if (isLoading) {
    return (
      <div className="p-6 flex items-center justify-center min-h-96">
        <div className="flex items-center gap-3 text-slate-600">
          <div className="w-6 h-6 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin" />
          جاري تحميل الديون...
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-6">
        <div className="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
          <p className="text-red-700 font-medium">{error}</p>
          <button
            onClick={loadDebts}
            className="mt-3 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition"
          >
            إعادة المحاولة
          </button>
        </div>
      </div>
    );
  }

  const debts = debtsData?.data || [];
  const meta = debtsData?.meta;

  return (
    <div className="p-6 space-y-6" dir="rtl">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900">الديون بين الأعضاء</h1>
          <p className="text-slate-500 mt-0.5 text-sm">Who Owes Who - حساب الديون مع التبسيط التلقائي</p>
        </div>
        <button
          onClick={loadDebts}
          className="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-200 transition text-sm"
        >
          <RefreshCw size={16} /> تحديث
        </button>
      </div>

      {/* Statistics Cards */}
      {meta && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm text-center">
            <div className="flex items-center justify-center w-10 h-10 bg-emerald-100 rounded-xl mx-auto mb-2">
              <DollarSign size={20} className="text-emerald-600" />
            </div>
            <p className="text-xs text-slate-500 mb-1">إجمالي الديون</p>
            <p className="text-xl font-extrabold text-slate-800">{parseFloat(meta.total_debts).toFixed(2)}</p>
            <p className="text-[10px] text-slate-400 mt-0.5">ر.س</p>
          </div>

          <div className="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm text-center">
            <div className="flex items-center justify-center w-10 h-10 bg-rose-100 rounded-xl mx-auto mb-2">
              <TrendingDown size={20} className="text-rose-600" />
            </div>
            <p className="text-xs text-rose-600 mb-1">المدينون</p>
            <p className="text-xl font-extrabold text-rose-700">{meta.active_debtors}</p>
            <p className="text-[10px] text-rose-400 mt-0.5">عضو</p>
          </div>

          <div className="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm text-center">
            <div className="flex items-center justify-center w-10 h-10 bg-indigo-100 rounded-xl mx-auto mb-2">
              <TrendingUp size={20} className="text-indigo-600" />
            </div>
            <p className="text-xs text-indigo-600 mb-1">الدائنون</p>
            <p className="text-xl font-extrabold text-indigo-700">{meta.active_creditors}</p>
            <p className="text-[10px] text-indigo-400 mt-0.5">عضو</p>
          </div>

          <div className="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm text-center">
            <div className="flex items-center justify-center w-10 h-10 bg-amber-100 rounded-xl mx-auto mb-2">
              <Users size={20} className="text-amber-600" />
            </div>
            <p className="text-xs text-amber-600 mb-1">علاقات الديون</p>
            <p className="text-xl font-extrabold text-amber-700">{meta.debt_relationships}</p>
            <p className="text-[10px] text-amber-400 mt-0.5">علاقة</p>
          </div>
        </div>
      )}

      {/* Debts List */}
      <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div className="p-4 border-b border-slate-100 bg-slate-50/50">
          <h2 className="text-lg font-bold text-slate-900">قائمة الديون المبسطة</h2>
          <p className="text-sm text-slate-500 mt-1">
            تم تطبيق التبسيط التلقائي (Netting) لإزالة الديون المتبادلة
          </p>
        </div>

        <div className="overflow-x-auto">
          {debts.length === 0 ? (
            <div className="py-16 text-center text-slate-400">
              <Users size={48} className="mx-auto mb-4 text-slate-300" />
              <p className="font-medium">لا توجد ديون بين الأعضاء</p>
              <p className="text-sm mt-1">جميع الحسابات متوازنة!</p>
            </div>
          ) : (
            <table className="w-full text-right">
              <thead>
                <tr className="bg-slate-700 text-white">
                  <th className="py-3.5 px-4 text-xs font-semibold uppercase tracking-wide text-right">المدين</th>
                  <th className="py-3.5 px-4 text-xs font-semibold uppercase tracking-wide text-right">الدائن</th>
                  <th className="py-3.5 px-4 text-xs font-semibold uppercase tracking-wide text-right">المبلغ</th>
                  <th className="py-3.5 px-4 text-xs font-semibold uppercase tracking-wide text-center">الحالة</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-50">
                {debts.map((debt, index) => (
                  <tr key={index} className="hover:bg-slate-50/60 transition-colors">
                    <td className="py-4 px-4">
                      <div className="flex items-center gap-3">
                        <div className="w-8 h-8 bg-rose-100 rounded-full flex items-center justify-center">
                          <span className="text-xs font-bold text-rose-600">
                            {debt.debtor_name.charAt(0)}
                          </span>
                        </div>
                        <div>
                          <p className="font-medium text-slate-800">{debt.debtor_name}</p>
                          <p className="text-xs text-slate-500">ID: {debt.debtor_id}</p>
                        </div>
                      </div>
                    </td>
                    <td className="py-4 px-4">
                      <div className="flex items-center gap-3">
                        <div className="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                          <span className="text-xs font-bold text-indigo-600">
                            {debt.creditor_name.charAt(0)}
                          </span>
                        </div>
                        <div>
                          <p className="font-medium text-slate-800">{debt.creditor_name}</p>
                          <p className="text-xs text-slate-500">ID: {debt.creditor_id}</p>
                        </div>
                      </div>
                    </td>
                    <td className="py-4 px-4">
                      <div className="text-left">
                        <span className="font-mono font-extrabold text-lg text-slate-800">
                          {parseFloat(debt.amount).toFixed(2)}
                        </span>
                        <span className="text-xs text-slate-400 font-normal mr-1">ر.س</span>
                      </div>
                    </td>
                    <td className="py-4 px-4 text-center">
                      <span className="px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                        مستحق
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>

      {/* Info Box */}
      <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <h3 className="font-bold text-blue-900 mb-2">كيف يعمل حساب الديون؟</h3>
        <div className="text-sm text-blue-800 space-y-1">
          <p>• يتم حساب الديون بناءً على المصروفات المشتركة فقط</p>
          <p>• يتم تطبيق التبسيط التلقائي (Netting) لإزالة الديون المتبادلة</p>
          <p>• المصروفات المدفوعة من الخزانة لا تؤثر على الديون بين الأعضاء</p>
          <p>• النتائج محدثة في الوقت الفعلي بناءً على آخر المصروفات</p>
        </div>
      </div>
    </div>
  );
}