import { useState } from 'react';
import { Plus, Search, CheckCircle2, Clock, ArrowDownToLine, ArrowUpFromLine } from 'lucide-react';
import { useAppStore } from '../../store/useAppStore';
import {
  formatAmount, formatDatetime, formatTxId,
  TX_TYPE_LABEL, TX_TYPE_COLOR, TX_STATUS_LABEL,
} from '../../utils/finance';
import { TransactionModal } from '../../components/shared/TransactionModal';

export function TransactionsPage() {
  const transactions = useAppStore((s) => s.transactions);
  const members = useAppStore((s) => s.members);
  const updateTransactionStatus = useAppStore((s) => s.updateTransactionStatus);

  const [showModal, setShowModal] = useState(false);
  const [filterStatus, setFilterStatus] = useState<string>('all');
  const [filterType, setFilterType] = useState<string>('all');
  const [filterMember, setFilterMember] = useState('');
  const [search, setSearch] = useState('');

  const filtered = transactions.filter((tx) => {
    if (filterStatus !== 'all' && tx.status !== filterStatus) return false;
    if (filterType !== 'all' && tx.type !== filterType) return false;
    if (filterMember && tx.memberId !== filterMember) return false;
    if (search && !tx.memberName.includes(search) && !tx.note.includes(search) && !tx.id.includes(search)) return false;
    return true;
  }).sort((a, b) => b.datetime.localeCompare(a.datetime));

  const totalCompleted = filtered
    .filter((t) => t.status === 'completed' && (t.type === 'deposit' || t.type === 'adjustment'))
    .reduce((s, t) => s + t.amount, 0) -
    filtered
    .filter((t) => t.status === 'completed' && (t.type === 'withdraw' || t.type === 'transfer'))
    .reduce((s, t) => s + t.amount, 0);

  return (
    <div className="p-8 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900">سجل العمليات</h1>
          <p className="text-slate-500 mt-1 text-sm">جميع العمليات المالية — إيداعات، سحوبات، تحويلات، تسويات</p>
        </div>
        <button
          onClick={() => setShowModal(true)}
          className="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-200 transition text-sm cursor-pointer"
        >
          <Plus size={18} />
          عملية جديدة
        </button>
      </div>

      {/* Quick Stats */}
      <div className="grid grid-cols-4 gap-4">
        <div className="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm text-center">
          <p className="text-xs text-slate-500">إجمالي العمليات</p>
          <p className="text-xl font-extrabold text-slate-800 mt-1">{filtered.length}</p>
        </div>
        <div className="bg-emerald-50 rounded-2xl p-4 border border-emerald-100 shadow-sm text-center">
          <p className="text-xs text-emerald-600">إيداعات</p>
          <p className="text-xl font-extrabold text-emerald-700 mt-1">{filtered.filter(t => t.type === 'deposit' || t.type === 'adjustment').length}</p>
        </div>
        <div className="bg-rose-50 rounded-2xl p-4 border border-rose-100 shadow-sm text-center">
          <p className="text-xs text-rose-600">سحوبات / تحويلات</p>
          <p className="text-xl font-extrabold text-rose-700 mt-1">{filtered.filter(t => t.type === 'withdraw' || t.type === 'transfer').length}</p>
        </div>
        <div className="bg-amber-50 rounded-2xl p-4 border border-amber-100 shadow-sm text-center">
          <p className="text-xs text-amber-600">معلقة</p>
          <p className="text-xl font-extrabold text-amber-700 mt-1">{filtered.filter(t => t.status === 'pending').length}</p>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex flex-wrap gap-3">
        {/* Search */}
        <div className="relative flex-1 min-w-44">
          <Search size={15} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            placeholder="بحث بالاسم أو المرجع أو البيان..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full pr-9 pl-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>

        {/* Type  */}
        <div className="flex rounded-xl bg-slate-100 p-1 gap-0.5">
          {['all', 'deposit', 'withdraw', 'transfer', 'adjustment'].map((t) => (
            <button
              key={t}
              onClick={() => setFilterType(t)}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer ${
                filterType === t ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-400 hover:text-slate-600'
              }`}
            >
              {t === 'all' ? 'كل الأنواع' : TX_TYPE_LABEL[t]}
            </button>
          ))}
        </div>

        {/* Status */}
        <div className="flex rounded-xl bg-slate-100 p-1 gap-0.5">
          {['all', 'completed', 'pending', 'cancelled'].map((s) => (
            <button
              key={s}
              onClick={() => setFilterStatus(s)}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer ${
                filterStatus === s ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-400 hover:text-slate-600'
              }`}
            >
              {s === 'all' ? 'كل الحالات' : TX_STATUS_LABEL[s]}
            </button>
          ))}
        </div>

        {/* Member */}
        <select
          value={filterMember}
          onChange={(e) => setFilterMember(e.target.value)}
          className="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="">كل الأعضاء</option>
          {members.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
        </select>
      </div>

      {/* Table */}
      <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-right min-w-[900px]">
            <thead>
              <tr className="bg-slate-700 text-white">
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">المرجع</th>
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">العضو</th>
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">النوع</th>
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">المبلغ</th>
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">التاريخ والوقت</th>
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">البيان</th>
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">الحالة</th>
                <th className="py-3.5 px-5 text-xs font-semibold uppercase tracking-wide">إجراء</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-50">
              {filtered.map((tx) => {
                const isIn = tx.type === 'deposit' || tx.type === 'adjustment';
                return (
                  <tr key={tx.id} className={`hover:bg-slate-50/60 transition-colors ${tx.status === 'cancelled' ? 'opacity-50' : ''}`}>
                    <td className="py-4 px-5">
                      <span className="font-mono text-xs font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded-lg">
                        {formatTxId(tx.id)}
                      </span>
                    </td>
                    <td className="py-4 px-5">
                      <div className="flex items-center gap-2.5">
                        <div className="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 font-bold text-xs flex items-center justify-center shrink-0">
                          {tx.memberName.charAt(0)}
                        </div>
                        <span className="font-semibold text-slate-800 text-sm">{tx.memberName}</span>
                      </div>
                    </td>
                    <td className="py-4 px-5">
                      <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ${TX_TYPE_COLOR[tx.type]}`}>
                        {isIn ? <ArrowDownToLine size={11} /> : <ArrowUpFromLine size={11} />}
                        {TX_TYPE_LABEL[tx.type]}
                      </span>
                    </td>
                    <td className="py-4 px-5">
                      <span className={`font-extrabold text-sm ${isIn ? 'text-emerald-600' : 'text-rose-600'}`} dir="ltr">
                        {isIn ? '+' : '-'}{formatAmount(tx.amount)} ر.س
                      </span>
                    </td>
                    <td className="py-4 px-5 text-slate-500 text-xs whitespace-nowrap">{formatDatetime(tx.datetime)}</td>
                    <td className="py-4 px-5 text-slate-600 text-sm">{tx.note}</td>
                    <td className="py-4 px-5">
                      <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${
                        tx.status === 'completed' ? 'bg-emerald-50 text-emerald-600' :
                        tx.status === 'pending'   ? 'bg-amber-50 text-amber-600'    :
                                                    'bg-slate-100 text-slate-400'
                      }`}>
                        {tx.status === 'completed' ? <CheckCircle2 size={12} /> : <Clock size={12} />}
                        {TX_STATUS_LABEL[tx.status]}
                      </span>
                    </td>
                    <td className="py-4 px-5">
                      {tx.status === 'pending' && (
                        <button
                          onClick={() => updateTransactionStatus(tx.id, 'completed')}
                          className="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-semibold rounded-lg transition cursor-pointer"
                        >
                          اعتماد
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        {filtered.length === 0 && (
          <div className="py-16 text-center text-slate-400 font-medium">لا توجد عمليات بهذه الفلاتر</div>
        )}
      </div>

      {showModal && <TransactionModal onClose={() => setShowModal(false)} />}
    </div>
  );
}
