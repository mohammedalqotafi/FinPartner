import { useState } from 'react';
import { Plus, Search, CheckCircle2, Clock, XCircle } from 'lucide-react';
import { useAppStore } from '../../store/useAppStore';
import {
  formatAmount, formatDatetime, formatTxId,
  TX_TYPE_LABEL, TX_TYPE_COLOR, TX_STATUS_LABEL,
} from '../../utils/finance';
import { TransactionModal } from '../../components/shared/TransactionModal';
import type { TransactionType } from '../../types';

interface Props {
  filterType: TransactionType;
}

export function FinancialPage({ filterType }: Props) {
  const transactions = useAppStore((s) => s.transactions);
  const members = useAppStore((s) => s.members);
  const updateTransactionStatus = useAppStore((s) => s.updateTransactionStatus);

  const [showModal, setShowModal] = useState(false);
  const [search, setSearch] = useState('');
  const [filterMember, setFilterMember] = useState('');
  const [filterStatus, setFilterStatus] = useState('all');

  const filtered = transactions.filter((tx) => {
    if (tx.type !== filterType) return false;
    if (filterMember && tx.memberId !== filterMember) return false;
    if (filterStatus !== 'all' && tx.status !== filterStatus) return false;
    if (search && !tx.memberName.includes(search) && !tx.note.includes(search) && !tx.id.includes(search)) return false;
    return true;
  }).sort((a, b) => b.datetime.localeCompare(a.datetime));

  const isDeposit = filterType === 'deposit';
  const total = filtered.filter((t) => t.status === 'completed').reduce((s, t) => s + t.amount, 0);
  const pending = filtered.filter((t) => t.status === 'pending').reduce((s, t) => s + t.amount, 0);

  return (
    <div className="p-8 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900">
            {isDeposit ? '💰 الإيداعات' : '💸 السحوبات'}
          </h1>
          <p className="text-slate-500 mt-1 text-sm">
            {isDeposit ? 'إدارة جميع مبالغ الإيداع للأعضاء' : 'إدارة جميع مبالغ السحب للأعضاء'}
          </p>
        </div>
        <button
          onClick={() => setShowModal(true)}
          className={`flex items-center gap-2 px-5 py-2.5 font-bold rounded-xl shadow-lg transition text-white text-sm cursor-pointer ${
            isDeposit ? 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-200' : 'bg-rose-600 hover:bg-rose-700 shadow-rose-200'
          }`}
        >
          <Plus size={18} />
          {isDeposit ? 'إيداع جديد' : 'سحب جديد'}
        </button>
      </div>

      {/* Summary */}
      <div className="grid grid-cols-3 gap-4">
        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
          <p className="text-xs text-slate-500 font-medium mb-1">إجمالي المكتمل</p>
          <p className={`text-2xl font-extrabold ${isDeposit ? 'text-emerald-600' : 'text-rose-600'}`} dir="ltr">
            {isDeposit ? '+' : '-'}{formatAmount(total)} ر.س
          </p>
        </div>
        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
          <p className="text-xs text-slate-500 font-medium mb-1">معلق التنفيذ</p>
          <p className="text-2xl font-extrabold text-amber-500" dir="ltr">{formatAmount(pending)} ر.س</p>
        </div>
        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
          <p className="text-xs text-slate-500 font-medium mb-1">عدد العمليات</p>
          <p className="text-2xl font-extrabold text-slate-800">{filtered.length}</p>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex flex-wrap gap-3">
        <div className="relative flex-1 min-w-44">
          <Search size={15} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            placeholder="بحث بالاسم أو رقم المرجع..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full pr-9 pl-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>
        <select
          value={filterMember}
          onChange={(e) => setFilterMember(e.target.value)}
          className="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="">كل الأعضاء</option>
          {members.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
        </select>
        <div className="flex rounded-xl bg-slate-100 p-1 gap-1">
          {(['all', 'completed', 'pending'] as const).map((s) => (
            <button
              key={s}
              onClick={() => setFilterStatus(s)}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer ${
                filterStatus === s ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'
              }`}
            >
              {s === 'all' ? 'الكل' : TX_STATUS_LABEL[s]}
            </button>
          ))}
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-right min-w-[750px]">
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
              {filtered.map((tx) => (
                <tr key={tx.id} className="hover:bg-slate-50/60 transition-colors">
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
                    <span className={`inline-flex px-2.5 py-1 rounded-full text-xs font-semibold ${TX_TYPE_COLOR[tx.type]}`}>
                      {TX_TYPE_LABEL[tx.type]}
                    </span>
                  </td>
                  <td className="py-4 px-5">
                    <span className={`font-extrabold text-sm ${isDeposit ? 'text-emerald-600' : 'text-rose-600'}`} dir="ltr">
                      {isDeposit ? '+' : '-'}{formatAmount(tx.amount)} ر.س
                    </span>
                  </td>
                  <td className="py-4 px-5 text-slate-500 text-xs whitespace-nowrap">{formatDatetime(tx.datetime)}</td>
                  <td className="py-4 px-5 text-slate-600 text-sm">{tx.note}</td>
                  <td className="py-4 px-5">
                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${
                      tx.status === 'completed' ? 'bg-emerald-50 text-emerald-600' :
                      tx.status === 'pending'   ? 'bg-amber-50 text-amber-600' :
                                                  'bg-slate-100 text-slate-400'
                    }`}>
                      {tx.status === 'completed' ? <CheckCircle2 size={12} /> :
                       tx.status === 'pending'   ? <Clock size={12} />         :
                                                   <XCircle size={12} />}
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
              ))}
            </tbody>
          </table>
        </div>
        {filtered.length === 0 && (
          <div className="py-16 text-center text-slate-400 font-medium">لا توجد عمليات</div>
        )}
      </div>

      {showModal && <TransactionModal defaultType={filterType} onClose={() => setShowModal(false)} />}
    </div>
  );
}
