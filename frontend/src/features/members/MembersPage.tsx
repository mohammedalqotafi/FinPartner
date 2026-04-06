import { useState } from 'react';
import { Plus, Mail, Phone, TrendingUp, TrendingDown, Minus, ExternalLink } from 'lucide-react';
import { useAppStore } from '../../store/useAppStore';
import { useNavigate } from 'react-router-dom';
import { formatAmount, calcSummary } from '../../utils/finance';

export function MembersPage() {
  const members = useAppStore((s) => s.members);
  const transactions = useAppStore((s) => s.transactions);
  const addMember = useAppStore((s) => s.addMember);
  const navigate = useNavigate();

  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ name: '', email: '', phone: '', openingBalance: '' });

  const handleAdd = (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.name.trim()) return;
    addMember({
      name: form.name.trim(),
      email: form.email || undefined,
      phone: form.phone || undefined,
      openingBalance: Number(form.openingBalance) || 0,
    });
    setForm({ name: '', email: '', phone: '', openingBalance: '' });
    setShowForm(false);
  };

  // Global stats
  const totalBalance = members.reduce((s, m) => s + m.balance, 0);
  const positiveCount = members.filter((m) => m.balance > 0).length;
  const negativeCount = members.filter((m) => m.balance < 0).length;

  return (
    <div className="p-8 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900">الأعضاء</h1>
          <p className="text-slate-500 mt-1 text-sm">إدارة حسابات أعضاء الفريق المالية</p>
        </div>
        <button
          onClick={() => setShowForm(!showForm)}
          className="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-200 transition text-sm cursor-pointer"
        >
          <Plus size={18} />
          إضافة عضو
        </button>
      </div>

      {/* Global Stats */}
      <div className="grid grid-cols-3 gap-4">
        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm text-center">
          <p className="text-xs text-slate-500 mb-1">إجمالي أرصدة الفريق</p>
          <p className={`text-2xl font-extrabold ${totalBalance >= 0 ? 'text-emerald-600' : 'text-rose-600'}`} dir="ltr">
            {formatAmount(totalBalance)} ر.س
          </p>
        </div>
        <div className="bg-emerald-50 rounded-2xl p-5 border border-emerald-100 text-center">
          <p className="text-xs text-emerald-600 mb-1">أرصدة موجبة</p>
          <p className="text-2xl font-extrabold text-emerald-700">{positiveCount} أعضاء</p>
        </div>
        <div className="bg-rose-50 rounded-2xl p-5 border border-rose-100 text-center">
          <p className="text-xs text-rose-600 mb-1">أرصدة سالبة</p>
          <p className="text-2xl font-extrabold text-rose-700">{negativeCount} أعضاء</p>
        </div>
      </div>

      {/* Add Member Form */}
      {showForm && (
        <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
          <h3 className="text-base font-bold text-slate-900 mb-4">بيانات العضو الجديد</h3>
          <form onSubmit={handleAdd} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input
              type="text"
              placeholder="الاسم *"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              className="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              required
            />
            <input
              type="email"
              placeholder="البريد الإلكتروني"
              value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
              className="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <input
              type="tel"
              placeholder="رقم الجوال"
              value={form.phone}
              onChange={(e) => setForm({ ...form, phone: e.target.value })}
              className="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <input
              type="number"
              placeholder="رصيد افتتاحي (اختياري)"
              value={form.openingBalance}
              onChange={(e) => setForm({ ...form, openingBalance: e.target.value })}
              className="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <div className="sm:col-span-2 lg:col-span-4 flex gap-3">
              <button type="submit" className="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition text-sm cursor-pointer">
                حفظ العضو
              </button>
              <button type="button" onClick={() => setShowForm(false)} className="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold rounded-xl transition text-sm cursor-pointer">
                إلغاء
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Members Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        {members.map((member) => {
          const memberTxs = transactions.filter((t) => t.memberId === member.id);
          const summary = calcSummary(member.openingBalance, memberTxs);
          const pendingCount = memberTxs.filter((t) => t.status === 'pending').length;
          const txCount = memberTxs.length;

          return (
            <div
              key={member.id}
              className="bg-white rounded-2xl border border-slate-100 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all overflow-hidden"
            >
              {/* Card Top */}
              <div className="p-5">
                <div className="flex items-start gap-3 mb-4">
                  <div className="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-400 to-violet-600 text-white font-bold text-xl flex items-center justify-center shrink-0">
                    {member.name.charAt(0)}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <h3 className="font-bold text-slate-900 truncate">{member.name}</h3>
                      {pendingCount > 0 && (
                        <span className="px-1.5 py-0.5 bg-amber-50 text-amber-600 text-[10px] font-bold rounded-full shrink-0">
                          {pendingCount} معلق
                        </span>
                      )}
                    </div>
                    <div className="flex flex-col gap-0.5 mt-1">
                      {member.email && (
                        <span className="text-xs text-slate-400 flex items-center gap-1 truncate"><Mail size={10} />{member.email}</span>
                      )}
                      {member.phone && (
                        <span className="text-xs text-slate-400 flex items-center gap-1"><Phone size={10} />{member.phone}</span>
                      )}
                    </div>
                  </div>
                </div>

                {/* Financial Summary */}
                <div className="grid grid-cols-2 gap-2.5 mb-4">
                  <div className="bg-emerald-50 rounded-xl px-3 py-2.5 text-center">
                    <p className="text-[10px] text-emerald-600 font-medium">إيداعات</p>
                    <p className="text-sm font-extrabold text-emerald-700 mt-0.5" dir="ltr">
                      +{formatAmount(summary.totalDeposits)}
                    </p>
                  </div>
                  <div className="bg-rose-50 rounded-xl px-3 py-2.5 text-center">
                    <p className="text-[10px] text-rose-600 font-medium">سحوبات</p>
                    <p className="text-sm font-extrabold text-rose-700 mt-0.5" dir="ltr">
                      -{formatAmount(summary.totalWithdrawals)}
                    </p>
                  </div>
                </div>

                {/* Balance */}
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-xs text-slate-500">الرصيد النهائي</p>
                    <div className="flex items-center gap-1.5 mt-0.5">
                      {member.balance > 0 ? <TrendingUp size={15} className="text-emerald-500" />
                        : member.balance < 0 ? <TrendingDown size={15} className="text-rose-500" />
                        : <Minus size={15} className="text-slate-400" />}
                      <span className={`font-extrabold text-lg ${
                        member.balance > 0 ? 'text-emerald-600' : member.balance < 0 ? 'text-rose-600' : 'text-slate-400'
                      }`} dir="ltr">
                        {formatAmount(member.balance)} ر.س
                      </span>
                    </div>
                  </div>
                  <div className="text-left">
                    <p className="text-xs text-slate-400">{txCount} عملية</p>
                    {member.openingBalance !== 0 && (
                      <p className="text-xs text-slate-400">افتتاحي: {formatAmount(member.openingBalance)}</p>
                    )}
                  </div>
                </div>

                {/* Balance bar */}
                <div className="mt-3 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                  {(() => {
                    const max = Math.max(...members.map((m) => Math.abs(m.balance)), 1);
                    const pct = (Math.abs(member.balance) / max) * 100;
                    return (
                      <div
                        className={`h-full rounded-full ${member.balance > 0 ? 'bg-emerald-400' : member.balance < 0 ? 'bg-rose-400' : 'bg-slate-300'}`}
                        style={{ width: `${pct}%` }}
                      />
                    );
                  })()}
                </div>
              </div>

              {/* Card Footer */}
              <div className="border-t border-slate-100 px-5 py-3 flex items-center justify-between">
                <span className="text-xs text-slate-400">عضو منذ {member.joinDate}</span>
                <button
                  onClick={() => navigate(`/members/${member.id}`)}
                  className="flex items-center gap-1.5 text-xs text-indigo-600 hover:text-indigo-700 font-semibold cursor-pointer"
                >
                  <ExternalLink size={13} />
                  كشف الحساب
                </button>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
