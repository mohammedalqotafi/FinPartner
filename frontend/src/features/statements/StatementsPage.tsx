import { useState } from 'react';
import { FileText, Printer } from 'lucide-react';
import { useAppStore } from '../../store/useAppStore';
import {
  buildLedger, calcSummary, formatAmount, formatDatetime, formatTxId,
  TX_TYPE_LABEL, TX_TYPE_COLOR, TX_STATUS_LABEL,
} from '../../utils/finance';

export function StatementsPage() {
  const members = useAppStore((s) => s.members);
  const transactions = useAppStore((s) => s.transactions);

  const [selectedMember, setSelectedMember] = useState('');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [filterType, setFilterType] = useState('all');

  const member = members.find((m) => m.id === selectedMember);

  const filtered = transactions.filter((tx) => {
    if (selectedMember && tx.memberId !== selectedMember) return false;
    if (filterType !== 'all' && tx.type !== filterType) return false;
    if (fromDate && tx.datetime < fromDate) return false;
    if (toDate && tx.datetime > toDate + 'T23:59:59') return false;
    if (tx.status === 'cancelled') return false;
    return true;
  }).sort((a, b) => a.datetime.localeCompare(b.datetime));

  // If per-member, use that member's opening balance; else 0
  const openingBalance = member ? member.openingBalance : 0;
  const ledger = buildLedger(openingBalance, filtered);

  // Totals
  const totalDeposits = filtered.filter(t => t.status === 'completed' && (t.type === 'deposit' || t.type === 'adjustment')).reduce((s, t) => s + t.amount, 0);
  const totalWithdrawals = filtered.filter(t => t.status === 'completed' && (t.type === 'withdraw' || t.type === 'transfer')).reduce((s, t) => s + t.amount, 0);
  const netChange = totalDeposits - totalWithdrawals;
  const finalBalance = openingBalance + netChange;

  return (
    <div className="p-8 space-y-6 print:p-4 print:space-y-4">

      {/* Header */}
      <div className="flex items-center justify-between print:hidden">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900 flex items-center gap-2">
            <FileText className="text-indigo-500" size={26} />
            كشف الحساب التفصيلي
          </h1>
          <p className="text-slate-500 mt-1 text-sm">دفتر الأستاذ العام — جميع العمليات المالية</p>
        </div>
        <button
          onClick={() => window.print()}
          className="flex items-center gap-2 px-5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl text-sm transition cursor-pointer"
        >
          <Printer size={16} />
          طباعة كشف الحساب (PDF)
        </button>
      </div>

      {/* Print Header */}
      <div className="hidden print:block text-center border-b-2 border-slate-800 pb-4">
        <h1 className="text-2xl font-bold">كشف الحساب التفصيلي</h1>
        {member && <p className="text-lg mt-1">العضو: {member.name}</p>}
        <p className="text-sm text-slate-500 mt-1">
          {fromDate || toDate ? `من ${fromDate || '—'} إلى ${toDate || '—'}` : 'جميع الفترات'}
        </p>
        <p className="text-sm mt-1">تاريخ الطباعة: {new Date().toLocaleDateString('ar-SA')}</p>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm print:hidden">
        <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">فلاتر الكشف</h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label className="block text-xs font-semibold text-slate-500 mb-1.5">العضو</label>
            <select
              value={selectedMember}
              onChange={(e) => setSelectedMember(e.target.value)}
              className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="">كل الأعضاء</option>
              {members.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
            </select>
          </div>
          <div>
            <label className="block text-xs font-semibold text-slate-500 mb-1.5">نوع العملية</label>
            <select
              value={filterType}
              onChange={(e) => setFilterType(e.target.value)}
              className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="all">الكل</option>
              <option value="deposit">إيداع</option>
              <option value="withdraw">سحب</option>
              <option value="transfer">تحويل</option>
              <option value="adjustment">تسوية</option>
            </select>
          </div>
          <div>
            <label className="block text-xs font-semibold text-slate-500 mb-1.5">من تاريخ</label>
            <input
              type="date"
              value={fromDate}
              onChange={(e) => setFromDate(e.target.value)}
              className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
          <div>
            <label className="block text-xs font-semibold text-slate-500 mb-1.5">إلى تاريخ</label>
            <input
              type="date"
              value={toDate}
              onChange={(e) => setToDate(e.target.value)}
              className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 print:grid-cols-4">
        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm print:border print:rounded-none print:shadow-none">
          <p className="text-xs text-slate-500 mb-1">الرصيد النهائي</p>
          <p className={`text-xl font-extrabold ${finalBalance >= 0 ? 'text-emerald-600' : 'text-rose-600'}`} dir="ltr">
            {formatAmount(finalBalance)} ر.س
          </p>
        </div>
        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm print:border print:rounded-none print:shadow-none">
          <p className="text-xs text-slate-500 mb-1">إجمالي الإيداعات</p>
          <p className="text-xl font-extrabold text-emerald-600" dir="ltr">+{formatAmount(totalDeposits)} ر.س</p>
        </div>
        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm print:border print:rounded-none print:shadow-none">
          <p className="text-xs text-slate-500 mb-1">إجمالي السحوبات</p>
          <p className="text-xl font-extrabold text-rose-600" dir="ltr">-{formatAmount(totalWithdrawals)} ر.س</p>
        </div>
        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm print:border print:rounded-none print:shadow-none">
          <p className="text-xs text-slate-500 mb-1">صافي التغير</p>
          <p className={`text-xl font-extrabold ${netChange >= 0 ? 'text-emerald-600' : 'text-rose-600'}`} dir="ltr">
            {netChange >= 0 ? '+' : ''}{formatAmount(netChange)} ر.س
          </p>
        </div>
      </div>

      {/* Member Summary (when no member selected) */}
      {!selectedMember && (
        <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-slate-100">
            <h3 className="font-bold text-slate-900">ملخص أرصدة الأعضاء</h3>
          </div>
          <div className="overflow-x-auto print:overflow-visible">
            <table className="w-full text-right text-sm print:min-w-0">
              <thead>
                <tr className="bg-slate-50 border-b border-slate-100">
                  <th className="py-3 px-6 text-xs font-semibold text-slate-500">العضو</th>
                  <th className="py-3 px-6 text-xs font-semibold text-slate-500">رصيد افتتاحي</th>
                  <th className="py-3 px-6 text-xs font-semibold text-emerald-600">إجمالي الإيداعات</th>
                  <th className="py-3 px-6 text-xs font-semibold text-rose-600">إجمالي السحوبات</th>
                  <th className="py-3 px-6 text-xs font-semibold text-slate-700">الرصيد الحالي</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-50">
                {members.map((m) => {
                  const s = calcSummary(m.openingBalance, transactions.filter(t => t.memberId === m.id));
                  return (
                    <tr key={m.id} className="hover:bg-slate-50/60 transition-colors">
                      <td className="py-3.5 px-6 font-semibold text-slate-800">{m.name}</td>
                      <td className="py-3.5 px-6 text-slate-500" dir="ltr">{formatAmount(m.openingBalance)} ر.س</td>
                      <td className="py-3.5 px-6 font-bold text-emerald-600" dir="ltr">+{formatAmount(s.totalDeposits)}</td>
                      <td className="py-3.5 px-6 font-bold text-rose-600" dir="ltr">-{formatAmount(s.totalWithdrawals)}</td>
                      <td className="py-3.5 px-6 font-extrabold" dir="ltr">
                        <span className={s.currentBalance >= 0 ? 'text-slate-800' : 'text-rose-600'}>
                          {formatAmount(s.currentBalance)} ر.س
                        </span>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Ledger Table */}
      <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden print:shadow-none print:border print:rounded-none">
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 className="font-bold text-slate-900">
            دفتر الأستاذ التفصيلي {member ? `— ${member.name}` : '(إجمالي)'}
          </h3>
          <span className="text-xs text-slate-400">{ledger.length - 1} عملية</span>
        </div>
        <div className="overflow-x-auto print:overflow-visible">
          <table className="w-full text-right min-w-[850px] print:min-w-0 text-sm">
            <thead>
              <tr className="bg-slate-700 text-white">
                <th className="py-3 px-5 text-xs font-semibold uppercase tracking-wide">المرجع</th>
                <th className="py-3 px-5 text-xs font-semibold uppercase tracking-wide">التاريخ والوقت</th>
                {!selectedMember && <th className="py-3 px-5 text-xs font-semibold uppercase tracking-wide">العضو</th>}
                <th className="py-3 px-5 text-xs font-semibold uppercase tracking-wide">النوع</th>
                <th className="py-3 px-5 text-xs font-semibold uppercase tracking-wide">البيان</th>
                <th className="py-3 px-5 text-xs font-semibold text-emerald-300">إيداع</th>
                <th className="py-3 px-5 text-xs font-semibold text-rose-300">سحب</th>
                <th className="py-3 px-5 text-xs font-semibold text-blue-200">الرصيد</th>
              </tr>
            </thead>
            <tbody>
              {ledger.map((row, i) => (
                <tr
                  key={row.isOpening ? 'opening' : row.tx!.id}
                  className={`border-b border-slate-50 transition-colors ${
                    row.isOpening ? 'bg-indigo-50/60 font-semibold' :
                    i === ledger.length - 1 ? 'bg-slate-50 font-semibold' : 'hover:bg-slate-50/50'
                  }`}
                >
                  <td className="py-3.5 px-5">
                    {row.isOpening ? (
                      <span className="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-bold">رصيد افتتاحي</span>
                    ) : (
                      <span className="font-mono text-xs font-bold text-slate-600">{formatTxId(row.tx!.id)}</span>
                    )}
                  </td>
                  <td className="py-3.5 px-5 text-slate-500 text-xs whitespace-nowrap">
                    {row.isOpening ? '—' : formatDatetime(row.tx!.datetime)}
                  </td>
                  {!selectedMember && (
                    <td className="py-3.5 px-5 text-sm font-semibold text-slate-700">
                      {row.isOpening ? '—' : row.tx!.memberName}
                    </td>
                  )}
                  <td className="py-3.5 px-5">
                    {row.isOpening ? (
                      <span className="text-xs text-indigo-500 font-semibold">افتتاحي</span>
                    ) : (
                      <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${TX_TYPE_COLOR[row.tx!.type]}`}>
                        {TX_TYPE_LABEL[row.tx!.type]}
                      </span>
                    )}
                  </td>
                  <td className="py-3.5 px-5 text-slate-600">
                    {row.isOpening ? <span className="text-indigo-600">رصيد البداية</span> : row.tx!.note}
                  </td>
                  <td className="py-3.5 px-5">
                    {row.credit > 0 ? (
                      <span className="font-bold text-emerald-600" dir="ltr">+{formatAmount(row.credit)}</span>
                    ) : <span className="text-slate-300 text-xs">—</span>}
                  </td>
                  <td className="py-3.5 px-5">
                    {row.debit > 0 ? (
                      <span className="font-bold text-rose-600" dir="ltr">-{formatAmount(row.debit)}</span>
                    ) : <span className="text-slate-300 text-xs">—</span>}
                  </td>
                  <td className={`py-3.5 px-5 ${i === ledger.length - 1 ? 'bg-slate-100/60' : ''}`}>
                    <span className={`font-extrabold ${row.running >= 0 ? 'text-slate-800' : 'text-rose-700'}`} dir="ltr">
                      {formatAmount(row.running)} ر.س
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr className="bg-slate-800 text-white">
                <td colSpan={!selectedMember ? 5 : 4} className="py-4 px-5 text-sm font-bold">الإجمالي</td>
                <td className="py-4 px-5 font-extrabold text-emerald-300" dir="ltr">+{formatAmount(totalDeposits)}</td>
                <td className="py-4 px-5 font-extrabold text-rose-300" dir="ltr">-{formatAmount(totalWithdrawals)}</td>
                <td className="py-4 px-5 font-extrabold text-blue-200" dir="ltr">{formatAmount(finalBalance)} ر.س</td>
              </tr>
            </tfoot>
          </table>
        </div>
        {ledger.length <= 1 && (
          <div className="py-12 text-center text-slate-400">لا توجد عمليات بهذه الفلاتر</div>
        )}
      </div>
    </div>
  );
}
