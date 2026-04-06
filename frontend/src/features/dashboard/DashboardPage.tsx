import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  ArrowDownToLine, ArrowUpFromLine, Users, TrendingUp, TrendingDown, ArrowUpDown,
  AlertCircle,
} from 'lucide-react';
import { useAppStore } from '../../store/useAppStore';
import { TransactionModal } from '../../components/shared/TransactionModal';
import { formatAmount, formatDatetime, TX_TYPE_LABEL, TX_TYPE_COLOR } from '../../utils/finance';
import type { TransactionType } from '../../types';

export function DashboardPage() {
  const members = useAppStore((s) => s.members);
  const transactions = useAppStore((s) => s.transactions);
  const [modal, setModal] = useState<TransactionType | null>(null);
  const navigate = useNavigate();

  const completed = transactions.filter((t) => t.status === 'completed');
  const totalDeposits = completed
    .filter((t) => t.type === 'deposit' || t.type === 'adjustment')
    .reduce((s, t) => s + t.amount, 0);
  const totalWithdrawals = completed
    .filter((t) => t.type === 'withdraw' || t.type === 'transfer')
    .reduce((s, t) => s + t.amount, 0);
  const totalBalance = members.reduce((s, m) => s + m.balance, 0);
  const netChange = totalDeposits - totalWithdrawals;
  const pendingCount = transactions.filter((t) => t.status === 'pending').length;

  const recent = [...transactions]
    .sort((a, b) => b.datetime.localeCompare(a.datetime))
    .slice(0, 8);

  return (
    <div className="p-8 space-y-8">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900">لوحة التحكم المالية</h1>
          <p className="text-slate-500 mt-1 text-sm">نظرة عامة على الوضع المالي للفريق</p>
        </div>
        <div className="flex gap-3">
          <button
            onClick={() => setModal('deposit')}
            className="flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-sm shadow-lg shadow-emerald-200 transition cursor-pointer"
          >
            <ArrowDownToLine size={16} /> إيداع سريع
          </button>
          <button
            onClick={() => setModal('withdraw')}
            className="flex items-center gap-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-sm shadow-lg shadow-rose-200 transition cursor-pointer"
          >
            <ArrowUpFromLine size={16} /> سحب سريع
          </button>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:-translate-y-0.5 transition-transform">
          <div className="flex items-center gap-3 mb-3">
            <div className="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center"><Users size={20} /></div>
            <p className="text-sm text-slate-500 font-medium">عدد الأعضاء</p>
          </div>
          <p className="text-3xl font-extrabold text-slate-900">{members.length}</p>
        </div>

        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:-translate-y-0.5 transition-transform">
          <div className="flex items-center gap-3 mb-3">
            <div className="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center"><TrendingUp size={20} /></div>
            <p className="text-sm text-slate-500 font-medium">إجمالي الإيداعات</p>
          </div>
          <p className="text-3xl font-extrabold text-emerald-600" dir="ltr">+{formatAmount(totalDeposits)}</p>
          <p className="text-xs text-slate-400 mt-1">ر.س</p>
        </div>

        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:-translate-y-0.5 transition-transform">
          <div className="flex items-center gap-3 mb-3">
            <div className="w-10 h-10 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center"><TrendingDown size={20} /></div>
            <p className="text-sm text-slate-500 font-medium">إجمالي السحوبات</p>
          </div>
          <p className="text-3xl font-extrabold text-rose-600" dir="ltr">-{formatAmount(totalWithdrawals)}</p>
          <p className="text-xs text-slate-400 mt-1">ر.س</p>
        </div>

        <div className="relative overflow-hidden bg-gradient-to-br from-indigo-600 to-violet-700 rounded-2xl p-5 shadow-xl shadow-indigo-200 hover:-translate-y-0.5 transition-transform">
          <div className="flex items-center gap-3 mb-3">
            <div className="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center text-white"><ArrowUpDown size={20} /></div>
            <p className="text-sm text-indigo-100 font-medium">إجمالي الأرصدة</p>
          </div>
          <p className="text-3xl font-extrabold text-white" dir="ltr">{formatAmount(totalBalance)}</p>
          <p className="text-xs text-indigo-200 mt-1">
            صافي التغير: {netChange >= 0 ? '+' : ''}{formatAmount(netChange)} ر.س
          </p>
        </div>
      </div>

      {/* Pending Alert */}
      {pendingCount > 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-center gap-3">
          <AlertCircle size={18} className="text-amber-500 shrink-0" />
          <p className="text-sm text-amber-800 font-semibold">
            {pendingCount} عملية معلقة غير محسوبة في الأرصدة — تحتاج اعتماداً
          </p>
          <Link to="/transactions" className="mr-auto text-sm font-bold text-amber-600 hover:underline">
            مراجعة المعلقة
          </Link>
        </div>
      )}

      {/* Bottom Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Recent Transactions */}
        <div className="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 className="font-bold text-slate-900">آخر العمليات</h2>
            <Link to="/transactions" className="text-sm text-indigo-600 hover:text-indigo-700 font-semibold">عرض الكل ←</Link>
          </div>
          <div className="divide-y divide-slate-50">
            {recent.map((tx) => (
              <div key={tx.id} className="flex items-center gap-4 px-6 py-3.5 hover:bg-slate-50/50 transition-colors">
                <div className="w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 font-bold text-sm flex items-center justify-center shrink-0">
                  {tx.memberName.charAt(0)}
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-semibold text-slate-800 text-sm truncate">{tx.memberName}</p>
                  <p className="text-xs text-slate-400 truncate">{tx.note} · {formatDatetime(tx.datetime)}</p>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                  <span className={`text-xs px-2 py-0.5 rounded-full font-semibold ${TX_TYPE_COLOR[tx.type]}`}>
                    {TX_TYPE_LABEL[tx.type]}
                  </span>
                  <span
                    className={`font-bold text-sm ${(tx.type === 'deposit' || tx.type === 'adjustment') ? 'text-emerald-600' : 'text-rose-600'}`}
                    dir="ltr"
                  >
                    {(tx.type === 'deposit' || tx.type === 'adjustment') ? '+' : '-'}{formatAmount(tx.amount)}
                  </span>
                  <span className={`text-xs px-2 py-0.5 rounded-full font-semibold ${tx.status === 'completed' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'}`}>
                    {tx.status === 'completed' ? 'مكتمل' : 'معلق'}
                  </span>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Members Balances */}
        <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 className="font-bold text-slate-900">أرصدة الأعضاء</h2>
            <Link to="/members" className="text-sm text-indigo-600 hover:text-indigo-700 font-semibold">إدارة ←</Link>
          </div>
          <div className="p-4 space-y-2">
            {members.map((m) => {
              const max = Math.max(...members.map((x) => Math.abs(x.balance)), 1);
              const pct = (Math.abs(m.balance) / max) * 100;
              return (
                <div
                  key={m.id}
                  onClick={() => navigate(`/members/${m.id}`)}
                  className="cursor-pointer hover:bg-slate-50 rounded-xl px-3 py-3 transition -mx-1"
                >
                  <div className="flex justify-between items-center mb-1.5">
                    <span className="text-sm font-semibold text-slate-700">{m.name}</span>
                    <span className={`text-sm font-extrabold ${m.balance >= 0 ? 'text-emerald-600' : 'text-rose-600'}`} dir="ltr">
                      {formatAmount(m.balance)} ر.س
                    </span>
                  </div>
                  <div className="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div
                      className={`h-full rounded-full ${m.balance >= 0 ? 'bg-emerald-400' : 'bg-rose-400'}`}
                      style={{ width: `${pct}%` }}
                    />
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>

      {modal && <TransactionModal defaultType={modal} onClose={() => setModal(null)} />}
    </div>
  );
}
