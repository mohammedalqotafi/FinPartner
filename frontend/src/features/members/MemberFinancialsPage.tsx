import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, useSearchParams } from 'react-router-dom';
import { ArrowLeft, TrendingUp, TrendingDown, Receipt, DollarSign, Calendar, User, Users } from 'lucide-react';
import api from '../../services/api';

interface MemberFinancials {
  member: {
    id: number;
    name: string;
    email?: string;
    phone?: string;
    opening_balance: number;
  };
  deposits: {
    total: number;
    count: number;
    items: Array<{
      id: number;
      reference: string;
      type: string;
      amount: number;
      date: string;
      note?: string;
    }>;
  };
  withdrawals: {
    total: number;
    count: number;
    items: Array<{
      id: number;
      reference: string;
      type: string;
      amount: number;
      date: string;
      note?: string;
    }>;
  };
  expenses: {
    paid: {
      total: number;
      your_share: number;
      paid_for_others: number;
      count: number;
      items: Array<any>;
    };
    owed: {
      total: number;
      count: number;
      items: Array<any>;
    };
    summary: {
      you_paid_total: number;
      your_share_from_paid: number;
      you_paid_for_others: number;
      you_owe_total: number;
    };
  };
  balances: {
    total_owed_to_you: number;
    total_you_owe: number;
    net_balance: number;
    count: number;
    items: Array<{
      member_id: number;
      member_name: string;
      you_paid_for_them: number;
      they_paid_for_you: number;
      net_balance: number;
      status: 'they_owe_you' | 'you_owe_them';
    }>;
  };
  summary: {
    opening_balance: number;
    total_deposits: number;
    total_withdrawals: number;
    total_paid_for_others: number;
    total_you_owe: number;
    net_balance: number;
    calculated_balance: number;
  };
}

export function MemberFinancialsPage() {
  const { memberId } = useParams<{ memberId: string }>();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const filterType = searchParams.get('filter') || 'all'; // all, deposits, withdrawals, expenses
  
  const [financials, setFinancials] = useState<MemberFinancials | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadFinancials();
  }, [memberId]);

  const loadFinancials = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await api.get(`/members/${memberId}/financials`);
      setFinancials(response.data);
    } catch (e: any) {
      console.error('Error loading financials:', e);
      setError(e.message || 'حدث خطأ أثناء تحميل التفاصيل المالية');
    } finally {
      setIsLoading(false);
    }
  };

  if (isLoading) {
    return (
      <div className="p-6 flex items-center justify-center min-h-96">
        <div className="flex items-center gap-3 text-slate-600">
          <div className="w-6 h-6 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin" />
          جاري التحميل...
        </div>
      </div>
    );
  }

  if (error || !financials) {
    return (
      <div className="p-6">
        <div className="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
          <p className="text-red-700 font-medium">{error || 'لم يتم العثور على البيانات'}</p>
          <button
            onClick={() => navigate('/members')}
            className="mt-3 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition"
          >
            العودة للأعضاء
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6" dir="rtl">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <button
            onClick={() => navigate(`/members/${memberId}`)}
            className="w-10 h-10 bg-slate-100 hover:bg-slate-200 rounded-xl flex items-center justify-center transition"
          >
            <ArrowLeft size={20} className="text-slate-600" />
          </button>
          <div>
            <h1 className="text-2xl font-extrabold text-slate-900">
              {filterType === 'deposits' && '💰 الإيداعات'}
              {filterType === 'withdrawals' && '💸 السحوبات'}
              {filterType === 'expenses' && '🧾 المصروفات'}
              {filterType === 'all' && 'التفاصيل المالية'}
            </h1>
            <p className="text-slate-500 mt-0.5 text-sm flex items-center gap-2">
              <User size={14} />
              {financials.member.name}
            </p>
          </div>
        </div>
        {filterType !== 'all' && (
          <button
            onClick={() => navigate(`/members/${memberId}/financials?filter=all`)}
            className="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 rounded-xl text-sm font-semibold transition"
          >
            عرض الكل
          </button>
        )}
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div className="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-5 text-white shadow-lg">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
              <DollarSign size={20} />
            </div>
            <p className="text-sm opacity-90">الرصيد الصافي</p>
          </div>
          <p className="text-3xl font-extrabold">{financials.summary.net_balance.toFixed(2)}</p>
          <p className="text-xs opacity-75 mt-1">ر.س</p>
        </div>

        <div className="bg-white rounded-2xl p-5 border border-emerald-100 shadow-sm">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
              <TrendingUp size={20} className="text-emerald-600" />
            </div>
            <p className="text-sm text-slate-600">الإيداعات</p>
          </div>
          <p className="text-2xl font-extrabold text-emerald-700">{financials.deposits.total.toFixed(2)}</p>
          <p className="text-xs text-emerald-500 mt-1">{financials.deposits.count} عملية</p>
        </div>

        <div className="bg-white rounded-2xl p-5 border border-rose-100 shadow-sm">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 bg-rose-100 rounded-xl flex items-center justify-center">
              <TrendingDown size={20} className="text-rose-600" />
            </div>
            <p className="text-sm text-slate-600">السحوبات</p>
          </div>
          <p className="text-2xl font-extrabold text-rose-700">{financials.withdrawals.total.toFixed(2)}</p>
          <p className="text-xs text-rose-500 mt-1">{financials.withdrawals.count} عملية</p>
        </div>

        <div className="bg-white rounded-2xl p-5 border border-amber-100 shadow-sm">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
              <Receipt size={20} className="text-amber-600" />
            </div>
            <p className="text-sm text-slate-600">دفعت للآخرين</p>
          </div>
          <p className="text-2xl font-extrabold text-amber-700">{financials.summary.total_paid_for_others.toFixed(2)}</p>
          <p className="text-xs text-amber-500 mt-1">ر.س</p>
        </div>

        <div className="bg-white rounded-2xl p-5 border border-blue-100 shadow-sm">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
              <Receipt size={20} className="text-blue-600" />
            </div>
            <p className="text-sm text-slate-600">مستحق عليك</p>
          </div>
          <p className="text-2xl font-extrabold text-blue-700">{financials.summary.total_you_owe.toFixed(2)}</p>
          <p className="text-xs text-blue-500 mt-1">ر.س</p>
        </div>

        <div className="bg-slate-50 rounded-2xl p-5 border border-slate-200 shadow-sm">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 bg-slate-200 rounded-xl flex items-center justify-center">
              <DollarSign size={20} className="text-slate-600" />
            </div>
            <p className="text-sm text-slate-600">رصيد افتتاحي</p>
          </div>
          <p className="text-2xl font-extrabold text-slate-700">{financials.summary.opening_balance.toFixed(2)}</p>
          <p className="text-xs text-slate-500 mt-1">ر.س</p>
        </div>
      </div>

      {/* Deposits Section */}
      {(filterType === 'all' || filterType === 'deposits') && financials.deposits.count > 0 && (
        <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div className="p-4 border-b border-slate-100 bg-emerald-50/50">
            <h2 className="text-lg font-bold text-slate-900 flex items-center gap-2">
              <TrendingUp size={20} className="text-emerald-600" />
              الإيداعات ({financials.deposits.count})
            </h2>
          </div>
          <div className="divide-y divide-slate-50">
            {financials.deposits.items.map((item) => (
              <div key={item.id} className="p-4 hover:bg-slate-50/60 transition flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                    <TrendingUp size={16} className="text-emerald-600" />
                  </div>
                  <div>
                    <p className="font-mono text-xs font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded">
                      {item.reference}
                    </p>
                    <p className="text-xs text-slate-500 mt-1 flex items-center gap-1">
                      <Calendar size={12} />
                      {new Date(item.date).toLocaleDateString('ar-SA')}
                    </p>
                    {item.note && <p className="text-xs text-slate-400 mt-0.5">{item.note}</p>}
                  </div>
                </div>
                <div className="text-left">
                  <span className="text-xl font-extrabold text-emerald-700">+{item.amount.toFixed(2)}</span>
                  <span className="text-xs text-emerald-500 mr-1">ر.س</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Withdrawals Section */}
      {(filterType === 'all' || filterType === 'withdrawals') && financials.withdrawals.count > 0 && (
        <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div className="p-4 border-b border-slate-100 bg-rose-50/50">
            <h2 className="text-lg font-bold text-slate-900 flex items-center gap-2">
              <TrendingDown size={20} className="text-rose-600" />
              السحوبات ({financials.withdrawals.count})
            </h2>
          </div>
          <div className="divide-y divide-slate-50">
            {financials.withdrawals.items.map((item) => (
              <div key={item.id} className="p-4 hover:bg-slate-50/60 transition flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-rose-100 rounded-full flex items-center justify-center">
                    <TrendingDown size={16} className="text-rose-600" />
                  </div>
                  <div>
                    <p className="font-mono text-xs font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded">
                      {item.reference}
                    </p>
                    <p className="text-xs text-slate-500 mt-1 flex items-center gap-1">
                      <Calendar size={12} />
                      {new Date(item.date).toLocaleDateString('ar-SA')}
                    </p>
                    {item.note && <p className="text-xs text-slate-400 mt-0.5">{item.note}</p>}
                  </div>
                </div>
                <div className="text-left">
                  <span className="text-xl font-extrabold text-rose-700">-{item.amount.toFixed(2)}</span>
                  <span className="text-xs text-rose-500 mr-1">ر.س</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Paid Expenses Section */}
      {(filterType === 'all' || filterType === 'expenses') && financials.expenses.paid.count > 0 && (
        <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div className="p-4 border-b border-slate-100 bg-amber-50/50">
            <h2 className="text-lg font-bold text-slate-900 flex items-center gap-2">
              <Receipt size={20} className="text-amber-600" />
              المصروفات المدفوعة ({financials.expenses.paid.count})
            </h2>
            <p className="text-sm text-amber-600 mt-1">
              دفعت للآخرين: {financials.expenses.paid.paid_for_others.toFixed(2)} ر.س
            </p>
          </div>
          <div className="divide-y divide-slate-50">
            {financials.expenses.paid.items.map((item) => (
              <div key={item.id} className="p-4 hover:bg-slate-50/60 transition">
                <div className="flex items-center justify-between mb-2">
                  <div>
                    <p className="font-mono text-xs font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded inline-block">
                      {item.reference}
                    </p>
                    <p className="text-sm font-bold text-slate-800 mt-1">{item.category}</p>
                  </div>
                  <div className="text-left">
                    <span className="text-lg font-extrabold text-amber-700">{item.total_amount.toFixed(2)}</span>
                    <span className="text-xs text-amber-500 mr-1">ر.س</span>
                  </div>
                </div>
                <div className="flex items-center gap-4 text-xs text-slate-500">
                  <span>نصيبك: {item.your_share.toFixed(2)} ر.س</span>
                  <span className="text-amber-600 font-bold">دفعت للآخرين: {item.paid_for_others.toFixed(2)} ر.س</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Owed Expenses Section */}
      {(filterType === 'all' || filterType === 'expenses') && financials.expenses.owed.count > 0 && (
        <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div className="p-4 border-b border-slate-100 bg-blue-50/50">
            <h2 className="text-lg font-bold text-slate-900 flex items-center gap-2">
              <Receipt size={20} className="text-blue-600" />
              المصروفات المستحقة ({financials.expenses.owed.count})
            </h2>
            <p className="text-sm text-blue-600 mt-1">
              إجمالي المستحق: {financials.expenses.owed.total.toFixed(2)} ر.س
            </p>
          </div>
          <div className="divide-y divide-slate-50">
            {financials.expenses.owed.items.map((item, index) => (
              <div key={index} className="p-4 hover:bg-slate-50/60 transition">
                <div className="flex items-center justify-between mb-2">
                  <div>
                    <p className="font-mono text-xs font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded inline-block">
                      {item.reference}
                    </p>
                    <p className="text-sm font-bold text-slate-800 mt-1">{item.category}</p>
                  </div>
                  <div className="text-left">
                    <span className="text-lg font-extrabold text-blue-700">{item.your_share.toFixed(2)}</span>
                    <span className="text-xs text-blue-500 mr-1">ر.س</span>
                  </div>
                </div>
                <p className="text-xs text-slate-500">
                  دفعه: <span className="font-bold text-slate-700">{item.paid_by}</span>
                </p>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Balances with Members Section - من يدين لمن */}
      {(filterType === 'all' || filterType === 'balances') && financials.balances && financials.balances.count > 0 && (
        <div className="bg-gradient-to-br from-slate-900 to-slate-800 rounded-3xl p-6 shadow-2xl border border-slate-700">
          {/* Header with Net Balance */}
          <div className="text-center mb-6 pb-6 border-b border-slate-700">
            <div className="flex items-center justify-center gap-2 mb-3">
              <User size={24} className="text-purple-400" />
              <h2 className="text-xl font-bold text-white">
                المستخدم الحالي: [{financials.member.name}]
              </h2>
            </div>
            
            <div className="bg-gradient-to-r from-rose-900/50 to-rose-800/50 border-2 border-rose-500 rounded-2xl p-4 mb-4">
              <p className="text-rose-200 text-sm mb-1">إجمالي الرصيد المالي</p>
              <p className={`text-4xl font-extrabold ${financials.balances.net_balance >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                {financials.balances.net_balance >= 0 ? '+' : ''}{financials.balances.net_balance.toFixed(2)} ر.س
              </p>
              <p className="text-slate-400 text-xs mt-2">
                (أنت دائن للمجموعة بشكل عام بهذا المبلغ)
              </p>
            </div>
          </div>

          {/* Summary Stats */}
          <div className="grid grid-cols-2 gap-4 mb-6">
            <div className="bg-rose-900/30 border border-rose-700 rounded-xl p-4">
              <div className="flex items-center gap-2 mb-2">
                <div className="w-8 h-8 bg-rose-500 rounded-lg flex items-center justify-center">
                  <TrendingDown size={16} className="text-white" />
                </div>
                <p className="text-rose-300 text-sm font-semibold">أموال عليك تسديدها</p>
              </div>
              <p className="text-2xl font-extrabold text-rose-400">
                -{financials.balances.total_you_owe.toFixed(2)} ر.س
              </p>
            </div>

            <div className="bg-emerald-900/30 border border-emerald-700 rounded-xl p-4">
              <div className="flex items-center gap-2 mb-2">
                <div className="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center">
                  <TrendingUp size={16} className="text-white" />
                </div>
                <p className="text-emerald-300 text-sm font-semibold">أموال مستحقة لك</p>
              </div>
              <p className="text-2xl font-extrabold text-emerald-400">
                +{financials.balances.total_owed_to_you.toFixed(2)} ر.س
              </p>
            </div>
          </div>

          {/* You Owe Section */}
          {financials.balances.items.filter(b => b.status === 'you_owe_them').length > 0 && (
            <div className="mb-6">
              <div className="flex items-center gap-2 mb-4">
                <TrendingDown size={20} className="text-rose-400" />
                <h3 className="text-lg font-bold text-rose-300">أموال عليك (للآخرين)</h3>
              </div>
              <div className="space-y-3">
                {financials.balances.items
                  .filter(b => b.status === 'you_owe_them')
                  .map((balance) => (
                    <div key={balance.member_id} className="bg-slate-800/50 border border-slate-700 rounded-xl p-4 hover:bg-slate-800 transition-all">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          <div className="w-12 h-12 bg-gradient-to-br from-rose-500 to-pink-600 rounded-full flex items-center justify-center">
                            <User size={20} className="text-white" />
                          </div>
                          <div>
                            <p className="font-bold text-white text-lg">{balance.member_name}</p>
                            <p className="text-rose-400 text-sm font-semibold">
                              دفع عنك: {balance.they_paid_for_you.toFixed(2)} ر.س
                            </p>
                          </div>
                        </div>
                        <div className="text-right">
                          <button className="bg-rose-500 hover:bg-rose-600 text-white px-6 py-2 rounded-lg font-bold text-sm transition-all shadow-lg">
                            تسديد
                          </button>
                          <p className="text-rose-400 font-extrabold text-2xl mt-2" dir="ltr">
                            -{Math.abs(balance.net_balance).toFixed(2)} ر.س
                          </p>
                        </div>
                      </div>
                    </div>
                  ))}
              </div>
            </div>
          )}

          {/* Owed to You Section */}
          {financials.balances.items.filter(b => b.status === 'they_owe_you').length > 0 && (
            <div>
              <div className="flex items-center gap-2 mb-4">
                <TrendingUp size={20} className="text-emerald-400" />
                <h3 className="text-lg font-bold text-emerald-300">أموال مستحقة لك</h3>
              </div>
              <div className="space-y-3">
                {financials.balances.items
                  .filter(b => b.status === 'they_owe_you')
                  .map((balance) => (
                    <div key={balance.member_id} className="bg-slate-800/50 border border-slate-700 rounded-xl p-4 hover:bg-slate-800 transition-all">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          <div className="w-12 h-12 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-full flex items-center justify-center">
                            <User size={20} className="text-white" />
                          </div>
                          <div>
                            <p className="font-bold text-white text-lg">{balance.member_name}</p>
                            <p className="text-emerald-400 text-sm font-semibold">
                              دفعت عنه: {balance.you_paid_for_them.toFixed(2)} ر.س
                            </p>
                          </div>
                        </div>
                        <div className="text-right">
                          <button className="bg-amber-500 hover:bg-amber-600 text-white px-6 py-2 rounded-lg font-bold text-sm transition-all shadow-lg flex items-center gap-2">
                            <span>🔔</span>
                            تذكير بالدفع
                          </button>
                          <p className="text-emerald-400 font-extrabold text-2xl mt-2" dir="ltr">
                            +{Math.abs(balance.net_balance).toFixed(2)} ر.س
                          </p>
                        </div>
                      </div>
                    </div>
                  ))}
              </div>
            </div>
          )}

          {/* Footer Note */}
          <div className="mt-6 pt-6 border-t border-slate-700">
            <div className="bg-blue-900/20 border border-blue-700 rounded-xl p-4">
              <p className="text-blue-300 text-sm text-center">
                <span className="font-bold">ملاحظة:</span> يمكنك الضغط على "تسديد" لتسجيل الدفع أو "تذكير" لإرسال إشعار للعضو
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
