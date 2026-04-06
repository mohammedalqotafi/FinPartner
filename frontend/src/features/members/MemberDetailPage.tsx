import { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  ArrowRight, ArrowDownToLine, ArrowUpFromLine,
  Printer, TrendingUp, TrendingDown, ArrowUpDown, Minus,
} from 'lucide-react';
import { useAppStore } from '../../store/useAppStore';
import { TransactionModal } from '../../components/shared/TransactionModal';
import {
  buildLedger, calcSummary, formatAmount, formatDatetime,
  formatTxId, TX_TYPE_LABEL, TX_TYPE_COLOR, TX_STATUS_LABEL,
} from '../../utils/finance';
import type { TransactionType } from '../../types';

export function MemberDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const members = useAppStore((s) => s.members);
  const transactions = useAppStore((s) => s.transactions);
  const updateTransactionStatus = useAppStore((s) => s.updateTransactionStatus);

  const [modal, setModal] = useState<TransactionType | null>(null);
  const [filterType, setFilterType] = useState('all');
  const [filterStatus, setFilterStatus] = useState('completed');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');

  const member = members.find((m) => m.id === id);

  if (!member) {
    return (
      <div className="p-8 text-center">
        <p className="text-slate-400 mb-4 text-lg">العضو غير موجود</p>
        <button onClick={() => navigate('/members')} className="text-indigo-600 text-sm font-semibold cursor-pointer">
          ← العودة للأعضاء
        </button>
      </div>
    );
  }

  // Filter & sort transactions for this member
  const memberTxs = transactions
    .filter((t) => t.memberId === id)
    .filter((t) => filterStatus === 'all' || t.status === filterStatus)
    .filter((t) => filterType === 'all' || t.type === filterType)
    .filter((t) => !fromDate || t.datetime >= fromDate)
    .filter((t) => !toDate || t.datetime <= toDate + 'T23:59:59')
    .sort((a, b) => a.datetime.localeCompare(b.datetime)); // ascending for running balance

  // Build ledger rows (opens with openingBalance)
  const ledger = buildLedger(member.openingBalance, memberTxs);

  // Summary from ALL completed transactions (no filter)
  const allMemberTxs = transactions.filter((t) => t.memberId === id);
  const summary = calcSummary(member.openingBalance, allMemberTxs);

  const pendingTxs = allMemberTxs.filter((t) => t.status === 'pending');

  return (
    <div className="p-6 lg:p-8 space-y-6 print:p-4 print:space-y-4">

      {/* ── Back + Print ── */}
      <div className="flex items-center justify-between print:hidden">
        <button
          onClick={() => navigate('/members')}
          className="flex items-center gap-2 text-slate-500 hover:text-slate-700 text-sm font-semibold transition cursor-pointer"
        >
          <ArrowRight size={16} />
          العودة للأعضاء
        </button>
        <button
          onClick={() => window.print()}
          className="flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-semibold rounded-xl text-sm transition cursor-pointer"
        >
          <Printer size={15} />
          طباعة كشف الحساب (PDF)
        </button>
      </div>

      {/* ── Print Header (shown only when printing) ── */}
      <div className="hidden print:block text-center border-b pb-4 mb-4">
        <h1 className="text-2xl font-bold">كشف حساب — {member.name}</h1>
        <p className="text-sm text-slate-500 mt-1">
          {fromDate && toDate ? `${fromDate} — ${toDate}` : 'جميع الفترات'}
        </p>
      </div>

      {/* ── Profile Card ── */}
      <div className="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm print:shadow-none print:border print:rounded-none">
        <div className="flex flex-wrap items-center gap-5">
          <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-400 to-violet-600 text-white font-bold text-2xl flex items-center justify-center shrink-0 print:hidden">
            {member.name.charAt(0)}
          </div>
          <div className="flex-1 min-w-0">
            <h1 className="text-xl font-extrabold text-slate-900">{member.name}</h1>
            <div className="flex flex-wrap gap-4 mt-1 text-xs text-slate-400">
              {member.email && <span>📧 {member.email}</span>}
              {member.phone && <span>📞 {member.phone}</span>}
              <span>📅 عضو منذ: {member.joinDate}</span>
            </div>
          </div>
          <div className="flex gap-3 print:hidden">
            <button
              onClick={() => setModal('deposit')}
              className="flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-sm shadow-lg shadow-emerald-200 transition cursor-pointer"
            >
              <ArrowDownToLine size={15} /> إيداع
            </button>
            <button
              onClick={() => setModal('withdraw')}
              className="flex items-center gap-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-sm shadow-lg shadow-rose-200 transition cursor-pointer"
            >
              <ArrowUpFromLine size={15} /> سحب
            </button>
          </div>
        </div>
      </div>

      {/* ── Summary Cards ── */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 print:grid-cols-4">
        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm print:border print:shadow-none print:rounded-none">
          <p className="text-xs text-slate-500 font-medium mb-1">الرصيد النهائي</p>
          <div className="flex items-center gap-1.5">
            {summary.currentBalance >= 0
              ? <TrendingUp size={18} className="text-emerald-500 shrink-0" />
              : <TrendingDown size={18} className="text-rose-500 shrink-0" />}
            <p className={`text-xl font-extrabold ${summary.currentBalance >= 0 ? 'text-emerald-600' : 'text-rose-600'}`} dir="ltr">
              {formatAmount(summary.currentBalance)} ر.س
            </p>
          </div>
        </div>

        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm print:border print:shadow-none print:rounded-none">
          <p className="text-xs text-slate-500 font-medium mb-1">إجمالي الإيداعات</p>
          <div className="flex items-center gap-1.5">
            <ArrowDownToLine size={16} className="text-emerald-500 shrink-0" />
            <p className="text-xl font-extrabold text-emerald-600" dir="ltr">+{formatAmount(summary.totalDeposits)} ر.س</p>
          </div>
        </div>

        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm print:border print:shadow-none print:rounded-none">
          <p className="text-xs text-slate-500 font-medium mb-1">إجمالي السحوبات</p>
          <div className="flex items-center gap-1.5">
            <ArrowUpFromLine size={16} className="text-rose-500 shrink-0" />
            <p className="text-xl font-extrabold text-rose-600" dir="ltr">-{formatAmount(summary.totalWithdrawals)} ر.س</p>
          </div>
        </div>

        <div className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm print:border print:shadow-none print:rounded-none">
          <p className="text-xs text-slate-500 font-medium mb-1">صافي التغير</p>
          <div className="flex items-center gap-1.5">
            <ArrowUpDown size={16} className={summary.netChange >= 0 ? 'text-emerald-500' : 'text-rose-500'} />
            <p className={`text-xl font-extrabold ${summary.netChange >= 0 ? 'text-emerald-600' : 'text-rose-600'}`} dir="ltr">
              {summary.netChange >= 0 ? '+' : ''}{formatAmount(summary.netChange)} ر.س
            </p>
          </div>
        </div>
      </div>

      {/* ── Pending Alert ── */}
      {pendingTxs.length > 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center gap-3 print:hidden">
          <span className="text-amber-500 text-lg">⏳</span>
          <p className="text-sm text-amber-800 font-semibold">
            {pendingTxs.length} عملية معلقة تحتاج اعتماداً (غير محسوبة في الرصيد)
          </p>
        </div>
      )}

      {/* ── Filters ── */}
      <div className="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm print:hidden">
        <div className="flex flex-wrap gap-3 items-center">
          <span className="text-xs font-bold text-slate-500 uppercase tracking-wide shrink-0">تصفية:</span>

          {/* Type */}
          <div className="flex rounded-xl bg-slate-100 p-1 gap-1">
            {(['all', 'deposit', 'withdraw', 'transfer', 'adjustment'] as const).map((t) => (
              <button
                key={t}
                onClick={() => setFilterType(t)}
                className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer ${filterType === t ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-400 hover:text-slate-600'}`}
              >
                {t === 'all' ? 'الكل' : TX_TYPE_LABEL[t]}
              </button>
            ))}
          </div>

          {/* Status */}
          <div className="flex rounded-xl bg-slate-100 p-1 gap-1">
            {(['completed', 'pending', 'all'] as const).map((s) => (
              <button
                key={s}
                onClick={() => setFilterStatus(s)}
                className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer ${filterStatus === s ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-400 hover:text-slate-600'}`}
              >
                {TX_STATUS_LABEL[s] ?? 'الكل'}
              </button>
            ))}
          </div>

          {/* Date range */}
          <input type="date" value={fromDate} onChange={(e) => setFromDate(e.target.value)}
            className="px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          <span className="text-slate-400 text-xs">→</span>
          <input type="date" value={toDate} onChange={(e) => setToDate(e.target.value)}
            className="px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          {(filterType !== 'all' || filterStatus !== 'completed' || fromDate || toDate) && (
            <button
              onClick={() => { setFilterType('all'); setFilterStatus('completed'); setFromDate(''); setToDate(''); }}
              className="text-xs text-rose-500 hover:text-rose-700 font-semibold cursor-pointer"
            >
              ✕ مسح الفلاتر
            </button>
          )}
        </div>
      </div>

      {/* ── Ledger Table ── */}
      <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden print:shadow-none print:border print:rounded-none">
        {/* Table Header Info */}
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 className="font-bold text-slate-900">دفتر الأستاذ — كشف الحساب التفصيلي</h2>
            <p className="text-xs text-slate-400 mt-0.5">{ledger.length - 1} عملية + رصيد افتتاحي</p>
          </div>
          <div className="text-xs text-slate-400">
            {fromDate && toDate ? `${fromDate} — ${toDate}` : 'كل الفترات'}
          </div>
        </div>

        <div className="overflow-x-auto print:overflow-visible">
          <table className="w-full text-right min-w-[900px] print:min-w-0 text-sm">
            <thead>
              <tr className="bg-slate-700 text-white">
                <th className="py-3 px-4 text-xs font-semibold uppercase tracking-wider text-right">المرجع</th>
                <th className="py-3 px-4 text-xs font-semibold uppercase tracking-wider">التاريخ والوقت</th>
                <th className="py-3 px-4 text-xs font-semibold uppercase tracking-wider">النوع</th>
                <th className="py-3 px-4 text-xs font-semibold uppercase tracking-wider">البيان / الوصف</th>
                <th className="py-3 px-4 text-xs font-semibold uppercase tracking-wider text-emerald-300">إيداع (دائن)</th>
                <th className="py-3 px-4 text-xs font-semibold uppercase tracking-wider text-rose-300">سحب (مدين)</th>
                <th className="py-3 px-4 text-xs font-semibold uppercase tracking-wider text-blue-200">الرصيد</th>
                <th className="py-3 px-4 text-xs font-semibold uppercase tracking-wider print:hidden">الحالة</th>
                <th className="py-3 px-4 text-xs font-semibold uppercase tracking-wider print:hidden">إجراء</th>
              </tr>
            </thead>
            <tbody>
              {ledger.map((row, i) => {
                const isOpeningRow = row.isOpening;
                const isLastRow = i === ledger.length - 1;
                const isPending = row.tx?.status === 'pending';

                return (
                  <tr
                    key={isOpeningRow ? 'opening' : row.tx!.id}
                    className={`
                      transition-colors border-b border-slate-50
                      ${isOpeningRow ? 'bg-indigo-50/60 font-semibold' : ''}
                      ${isLastRow && !isOpeningRow ? 'bg-slate-50 font-semibold' : ''}
                      ${isPending ? 'opacity-60 italic' : ''}
                      ${!isOpeningRow && !isLastRow && !isPending ? 'hover:bg-slate-50/70' : ''}
                    `}
                  >
                    {/* Reference */}
                    <td className="py-3.5 px-4">
                      {isOpeningRow ? (
                        <span className="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-bold">رصيد افتتاحي</span>
                      ) : (
                        <span className="font-mono text-xs font-bold text-slate-600">{formatTxId(row.tx!.id)}</span>
                      )}
                    </td>

                    {/* DateTime */}
                    <td className="py-3.5 px-4 text-slate-500 text-xs whitespace-nowrap">
                      {isOpeningRow ? '—' : formatDatetime(row.tx!.datetime)}
                    </td>

                    {/* Type Badge */}
                    <td className="py-3.5 px-4">
                      {isOpeningRow ? (
                        <span className="text-xs text-indigo-500 font-semibold">افتتاحي</span>
                      ) : (
                        <span className={`px-2.5 py-1 rounded-full text-xs font-semibold ${TX_TYPE_COLOR[row.tx!.type]}`}>
                          {TX_TYPE_LABEL[row.tx!.type]}
                        </span>
                      )}
                    </td>

                    {/* Note */}
                    <td className="py-3.5 px-4 text-slate-600">
                      {isOpeningRow ? (
                        <span className="text-indigo-600 font-semibold">رصيد البداية</span>
                      ) : (
                        row.tx!.note
                      )}
                    </td>

                    {/* Credit (Deposit) */}
                    <td className="py-3.5 px-4">
                      {row.credit > 0 ? (
                        <span className="font-bold text-emerald-600" dir="ltr">+{formatAmount(row.credit)}</span>
                      ) : (
                        <span className="text-slate-300 text-xs">—</span>
                      )}
                    </td>

                    {/* Debit (Withdraw) */}
                    <td className="py-3.5 px-4">
                      {row.debit > 0 ? (
                        <span className="font-bold text-rose-600" dir="ltr">-{formatAmount(row.debit)}</span>
                      ) : (
                        <span className="text-slate-300 text-xs">—</span>
                      )}
                    </td>

                    {/* Running Balance */}
                    <td className={`py-3.5 px-4 ${isLastRow ? 'bg-slate-100' : ''}`}>
                      <span
                        className={`font-extrabold ${row.running >= 0 ? 'text-slate-800' : 'text-rose-700'} ${isLastRow ? 'text-base' : ''}`}
                        dir="ltr"
                      >
                        {formatAmount(row.running)} ر.س
                      </span>
                      {isLastRow && (
                        <span className="block text-xs text-slate-400 font-normal mt-0.5">الرصيد النهائي</span>
                      )}
                    </td>

                    {/* Status */}
                    <td className="py-3.5 px-4 print:hidden">
                      {!isOpeningRow && (
                        <span className={`text-xs px-2 py-0.5 rounded-full font-semibold ${
                          row.tx!.status === 'completed' ? 'bg-emerald-50 text-emerald-600' :
                          row.tx!.status === 'pending'   ? 'bg-amber-50 text-amber-600' :
                                                           'bg-slate-100 text-slate-400'
                        }`}>
                          {TX_STATUS_LABEL[row.tx!.status]}
                        </span>
                      )}
                    </td>

                    {/* Action */}
                    <td className="py-3.5 px-4 print:hidden">
                      {!isOpeningRow && row.tx!.status === 'pending' && (
                        <button
                          onClick={() => updateTransactionStatus(row.tx!.id, 'completed')}
                          className="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-semibold rounded-lg transition cursor-pointer whitespace-nowrap"
                        >
                          اعتماد
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>

            {/* Totals Footer */}
            {ledger.length > 1 && (
              <tfoot>
                <tr className="bg-slate-800 text-white">
                  <td colSpan={4} className="py-4 px-4 text-sm font-bold">الإجمالي</td>
                  <td className="py-4 px-4 font-extrabold text-emerald-300" dir="ltr">
                    +{formatAmount(ledger.slice(1).reduce((s, r) => s + r.credit, 0))}
                  </td>
                  <td className="py-4 px-4 font-extrabold text-rose-300" dir="ltr">
                    -{formatAmount(ledger.slice(1).reduce((s, r) => s + r.debit, 0))}
                  </td>
                  <td className="py-4 px-4 font-extrabold text-blue-200" dir="ltr">
                    {formatAmount(ledger[ledger.length - 1].running)} ر.س
                  </td>
                  <td colSpan={2} className="print:hidden" />
                </tr>
              </tfoot>
            )}
          </table>
        </div>

        {ledger.length <= 1 && (
          <div className="py-16 text-center text-slate-400 font-medium">
            لا توجد عمليات لهذا العضو بهذه الفلاتر
          </div>
        )}
      </div>

      {modal && (
        <TransactionModal
          defaultType={modal}
          defaultMemberId={id}
          onClose={() => setModal(null)}
        />
      )}
    </div>
  );
}
