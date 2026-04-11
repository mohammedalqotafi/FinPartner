import React, { useState, useEffect, useMemo } from 'react';
import { Plus, Search, Eye, Trash2, ChevronUp, ChevronDown, ChevronsUpDown, ChevronLeft, ChevronRight } from 'lucide-react';
import { expensesService, PaginatedExpenseResponse } from '../../services/expenses.service';
import type { Expense, ExpenseType } from '../../types/expenses.types';
import { ExpenseForm } from './components/ExpenseForm';
import { ExpenseDetails } from './components/ExpenseDetails';
import { ExpenseDetailsModal } from './components/ExpenseDetailsModal';

// ─── Types ────────────────────────────────────────────────────────────────────
type SortField = 'expense_datetime' | 'amount' | 'reference' | 'category';
type SortDir   = 'asc' | 'desc';

// ─── Helpers ──────────────────────────────────────────────────────────────────
const TYPE_LABELS: Record<string, string> = {
  all:         'الكل',
  shared:      'مشترك',
  operational: 'تشغيلي',
  personal:    'شخصي',
};

const TYPE_COLORS: Record<ExpenseType, string> = {
  shared:      'bg-indigo-50 text-indigo-700 border-indigo-200',
  operational: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  personal:    'bg-rose-50 text-rose-700 border-rose-200',
};

function SortIcon({ field, active, dir }: { field: string; active: boolean; dir: SortDir }) {
  if (!active) return <ChevronsUpDown size={13} className="text-slate-300 inline mr-1" />;
  return dir === 'asc'
    ? <ChevronUp size={13} className="text-indigo-400 inline mr-1" />
    : <ChevronDown size={13} className="text-indigo-400 inline mr-1" />;
}

// ─── ExpensesPage ─────────────────────────────────────────────────────────────
export function ExpensesPage() {
  const [expenses, setExpenses]     = useState<Expense[]>([]);
  const [isLoading, setIsLoading]   = useState(true);
  const [showForm, setShowForm]     = useState(false);
  const [viewExpense, setViewExpense] = useState<Expense | null>(null);
  const [selectedExpenseId, setSelectedExpenseId] = useState<number | null>(null);
  const [currentUserId, setCurrentUserId] = useState<number | undefined>(undefined); // يمكن تعيينه من context أو props

  // ─── Pagination (15.4) ─────────────────────────────────────────────────────
  const [currentPage, setCurrentPage] = useState(1);
  const [perPage, setPerPage]         = useState(25);
  const [totalPages, setTotalPages]   = useState(1);
  const [totalItems, setTotalItems]   = useState(0);
  const [hasMorePages, setHasMorePages] = useState(false);

  // ─── Filters (18.2) ────────────────────────────────────────────────────────
  const [search, setSearch]         = useState('');
  const [filterType, setFilterType] = useState('all');
  const [dateFrom, setDateFrom]     = useState('');
  const [dateTo, setDateTo]         = useState('');

  // ─── Sorting (18.1) ────────────────────────────────────────────────────────
  const [sortField, setSortField]   = useState<SortField>('expense_datetime');
  const [sortDir, setSortDir]       = useState<SortDir>('desc');

  const loadExpenses = async (page: number = currentPage) => {
    setIsLoading(true);
    try {
      // Load all expenses for client-side filtering and statistics
      // In a real-world scenario, you might want to implement server-side filtering
      const data = await expensesService.getAll({ all: true });
      setExpenses(data);
      
      // Calculate pagination for filtered results
      const filteredCount = filtered.length;
      setTotalItems(filteredCount);
      setTotalPages(Math.ceil(filteredCount / perPage));
      setHasMorePages(page < Math.ceil(filteredCount / perPage));
    } catch (e) {
      console.error(e);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => { loadExpenses(); }, []);
  
  // Reset to first page when filters change
  useEffect(() => {
    setCurrentPage(1);
  }, [search, filterType, dateFrom, dateTo, sortField, sortDir]);

  const handleSort = (field: SortField) => {
    if (sortField === field) {
      setSortDir(d => d === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortDir('desc');
    }
  };

  const handleDelete = async (exp: Expense) => {
    if (!confirm(`حذف المصروف ${exp.reference}؟`)) return;
    try {
      await expensesService.delete(exp.id);
      await loadExpenses();
    } catch (e) {
      console.error(e);
    }
  };

  // ─── Filtered + Sorted + Paginated list ────────────────────────────────────
  const filtered = useMemo(() => {
    let list = expenses.filter(exp => {
      if (filterType !== 'all' && exp.expense_type !== filterType) return false;
      if (search) {
        const q = search.toLowerCase();
        if (
          !exp.reference.toLowerCase().includes(q) &&
          !exp.category.toLowerCase().includes(q) &&
          !(exp.payer?.name?.toLowerCase().includes(q))
        ) return false;
      }
      if (dateFrom && exp.expense_datetime < dateFrom) return false;
      if (dateTo   && exp.expense_datetime > dateTo + 'T23:59:59') return false;
      return true;
    });

    list = [...list].sort((a, b) => {
      let va: string | number = a[sortField] as string;
      let vb: string | number = b[sortField] as string;
      if (sortField === 'amount') { va = parseFloat(a.amount); vb = parseFloat(b.amount); }
      if (va < vb) return sortDir === 'asc' ? -1 : 1;
      if (va > vb) return sortDir === 'asc' ? 1 : -1;
      return 0;
    });

    return list;
  }, [expenses, filterType, search, dateFrom, dateTo, sortField, sortDir]);

  // ─── Paginated results ─────────────────────────────────────────────────────
  const paginatedResults = useMemo(() => {
    const startIndex = (currentPage - 1) * perPage;
    const endIndex = startIndex + perPage;
    return filtered.slice(startIndex, endIndex);
  }, [filtered, currentPage, perPage]);

  // Update pagination info when filtered results change
  useEffect(() => {
    const filteredCount = filtered.length;
    setTotalItems(filteredCount);
    setTotalPages(Math.ceil(filteredCount / perPage));
    setHasMorePages(currentPage < Math.ceil(filteredCount / perPage));
  }, [filtered, currentPage, perPage]);

  // ─── Statistics (18.3) ─────────────────────────────────────────────────────
  const stats = useMemo(() => ({
    total:       filtered.reduce((s, e) => s + parseFloat(e.amount), 0),
    shared:      filtered.filter(e => e.expense_type === 'shared').reduce((s, e) => s + parseFloat(e.amount), 0),
    operational: filtered.filter(e => e.expense_type === 'operational').reduce((s, e) => s + parseFloat(e.amount), 0),
    personal:    filtered.filter(e => e.expense_type === 'personal').reduce((s, e) => s + parseFloat(e.amount), 0),
    count:       filtered.length,
  }), [filtered]);

  // ─── Pagination handlers ───────────────────────────────────────────────────
  const handlePageChange = (page: number) => {
    if (page >= 1 && page <= totalPages) {
      setCurrentPage(page);
    }
  };

  const handlePerPageChange = (newPerPage: number) => {
    setPerPage(newPerPage);
    setCurrentPage(1); // Reset to first page
  };

  const thClass = "py-3.5 px-4 text-xs font-semibold uppercase tracking-wide text-right cursor-pointer select-none hover:bg-slate-600 transition-colors";

  return (
    <div className="p-6 space-y-5" dir="rtl">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900">سجل المصروفات</h1>
          <p className="text-slate-500 mt-0.5 text-sm">دفتر الأستاذ الموحد — تشغيلي · مشترك · شخصي</p>
        </div>
        <button
          onClick={() => setShowForm(true)}
          className="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-200 transition text-sm cursor-pointer"
        >
          <Plus size={18} /> إضافة مصروف
        </button>
      </div>

      {/* Statistics Cards (18.3) */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4" data-testid="stats-cards">
        <div className="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm text-center">
          <p className="text-xs text-slate-500 mb-1">إجمالي المعروض</p>
          <p className="text-xl font-extrabold text-slate-800">{stats.total.toFixed(2)}</p>
          <p className="text-[10px] text-slate-400 mt-0.5">{stats.count} سجل</p>
        </div>
        <div className="bg-indigo-50 rounded-2xl p-4 border border-indigo-100 shadow-sm text-center">
          <p className="text-xs text-indigo-600 mb-1">مشترك (Shared)</p>
          <p className="text-xl font-extrabold text-indigo-700">{stats.shared.toFixed(2)}</p>
          <p className="text-[10px] text-indigo-400 mt-0.5">ر.س</p>
        </div>
        <div className="bg-emerald-50 rounded-2xl p-4 border border-emerald-100 shadow-sm text-center">
          <p className="text-xs text-emerald-600 mb-1">تشغيلي (Operational)</p>
          <p className="text-xl font-extrabold text-emerald-700">{stats.operational.toFixed(2)}</p>
          <p className="text-[10px] text-emerald-400 mt-0.5">ر.س</p>
        </div>
        <div className="bg-rose-50 rounded-2xl p-4 border border-rose-100 shadow-sm text-center">
          <p className="text-xs text-rose-600 mb-1">شخصي (Personal)</p>
          <p className="text-xl font-extrabold text-rose-700">{stats.personal.toFixed(2)}</p>
          <p className="text-[10px] text-rose-400 mt-0.5">ر.س</p>
        </div>
      </div>

      {/* Filters (18.2) */}
      <div className="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex flex-wrap gap-3 items-center">
        {/* Search */}
        <div className="relative flex-1 min-w-44">
          <Search size={15} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            placeholder="بحث بالمرجع أو التصنيف أو الدافع..."
            value={search}
            onChange={e => setSearch(e.target.value)}
            className="w-full pr-9 pl-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>

        {/* Type filter */}
        <div className="flex rounded-xl bg-slate-100 p-1 gap-0.5">
          {['all', 'shared', 'operational', 'personal'].map(t => (
            <button
              key={t}
              onClick={() => setFilterType(t)}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer ${filterType === t ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-400 hover:text-slate-600'}`}
            >
              {TYPE_LABELS[t]}
            </button>
          ))}
        </div>

        {/* Date range filter (18.2) */}
        <div className="flex items-center gap-2 text-xs text-slate-500">
          <span>من</span>
          <input
            type="date"
            value={dateFrom}
            onChange={e => setDateFrom(e.target.value)}
            className="border border-slate-200 rounded-lg px-2 py-1.5 text-xs bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-400"
          />
          <span>إلى</span>
          <input
            type="date"
            value={dateTo}
            onChange={e => setDateTo(e.target.value)}
            className="border border-slate-200 rounded-lg px-2 py-1.5 text-xs bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-400"
          />
          {(dateFrom || dateTo) && (
            <button
              onClick={() => { setDateFrom(''); setDateTo(''); }}
              className="text-rose-500 hover:text-rose-700 text-xs font-bold cursor-pointer"
            >
              مسح
            </button>
          )}
        </div>
      </div>

      {/* Table (18.1) */}
      <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-right min-w-[900px]">
            <thead>
              <tr className="bg-slate-700 text-white">
                <th className={thClass} onClick={() => handleSort('reference')}>
                  المرجع <SortIcon field="reference" active={sortField === 'reference'} dir={sortDir} />
                </th>
                <th className={thClass} onClick={() => handleSort('expense_datetime')}>
                  التاريخ <SortIcon field="expense_datetime" active={sortField === 'expense_datetime'} dir={sortDir} />
                </th>
                <th className="py-3.5 px-4 text-xs font-semibold uppercase tracking-wide text-right">النوع</th>
                <th className={thClass} onClick={() => handleSort('category')}>
                  التصنيف <SortIcon field="category" active={sortField === 'category'} dir={sortDir} />
                </th>
                <th className={thClass} onClick={() => handleSort('amount')}>
                  المبلغ <SortIcon field="amount" active={sortField === 'amount'} dir={sortDir} />
                </th>
                <th className="py-3.5 px-4 text-xs font-semibold uppercase tracking-wide text-right">الدافع</th>
                <th className="py-3.5 px-4 text-xs font-semibold uppercase tracking-wide text-center">إجراء</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-50">
              {isLoading ? (
                <tr>
                  <td colSpan={7} className="py-16 text-center text-slate-400 font-medium">
                    <div className="flex items-center justify-center gap-2">
                      <div className="w-4 h-4 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin" />
                      جاري التحميل...
                    </div>
                  </td>
                </tr>
              ) : paginatedResults.map(exp => (
                <tr key={exp.id} className="hover:bg-slate-50/60 transition-colors group">
                  <td className="py-3.5 px-4">
                    <span className="font-mono text-xs font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded-lg">
                      {exp.reference}
                    </span>
                  </td>
                  <td className="py-3.5 px-4 text-slate-500 text-xs whitespace-nowrap">
                    {new Date(exp.expense_datetime).toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' })}
                    <span className="block text-[10px] text-slate-400">
                      {new Date(exp.expense_datetime).toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' })}
                    </span>
                  </td>
                  <td className="py-3.5 px-4">
                    <span className={`px-2.5 py-1 rounded-full text-xs font-semibold border ${TYPE_COLORS[exp.expense_type]}`}>
                      {TYPE_LABELS[exp.expense_type]}
                    </span>
                  </td>
                  <td className="py-3.5 px-4 text-slate-800 text-sm font-medium">{exp.category}</td>
                  <td className="py-3.5 px-4 font-mono font-extrabold text-sm text-slate-800">
                    {parseFloat(exp.amount).toFixed(2)}
                    <span className="text-[10px] text-slate-400 font-normal mr-1">ر.س</span>
                  </td>
                  <td className="py-3.5 px-4 text-sm text-slate-600 font-medium">
                    {exp.payer ? exp.payer.name : <span className="text-slate-400 text-xs">خزانة</span>}
                  </td>
                  <td className="py-3.5 px-4">
                    <div className="flex items-center justify-center gap-1.5">
                      <button
                        onClick={() => setSelectedExpenseId(exp.id)}
                        className="p-1.5 bg-slate-100 hover:bg-indigo-100 hover:text-indigo-600 text-slate-500 rounded-lg transition cursor-pointer"
                        title="عرض التفاصيل"
                      >
                        <Eye size={15} />
                      </button>
                      <button
                        onClick={() => handleDelete(exp)}
                        className="p-1.5 bg-slate-100 hover:bg-rose-100 hover:text-rose-600 text-slate-500 rounded-lg transition cursor-pointer opacity-0 group-hover:opacity-100"
                        title="حذف"
                      >
                        <Trash2 size={15} />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {!isLoading && paginatedResults.length === 0 && (
                <tr>
                  <td colSpan={7} className="py-16 text-center text-slate-400 font-medium">
                    لا توجد سجلات مطابقة
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Pagination Controls (15.4) */}
      {totalPages > 1 && (
        <div className="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="text-sm text-slate-600">
              عرض {((currentPage - 1) * perPage) + 1} إلى {Math.min(currentPage * perPage, totalItems)} من {totalItems} سجل
            </div>
            <div className="flex items-center gap-2">
              <span className="text-sm text-slate-500">عرض</span>
              <select
                value={perPage}
                onChange={(e) => handlePerPageChange(Number(e.target.value))}
                className="border border-slate-200 rounded-lg px-2 py-1 text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-400"
              >
                <option value={10}>10</option>
                <option value={25}>25</option>
                <option value={50}>50</option>
                <option value={100}>100</option>
              </select>
              <span className="text-sm text-slate-500">سجل</span>
            </div>
          </div>
          
          <div className="flex items-center gap-2">
            <button
              onClick={() => handlePageChange(1)}
              disabled={currentPage === 1}
              className="p-2 text-slate-400 hover:text-slate-600 disabled:opacity-50 disabled:cursor-not-allowed transition"
              title="الصفحة الأولى"
            >
              <ChevronsUpDown size={16} className="rotate-90" />
            </button>
            <button
              onClick={() => handlePageChange(currentPage - 1)}
              disabled={currentPage === 1}
              className="p-2 text-slate-400 hover:text-slate-600 disabled:opacity-50 disabled:cursor-not-allowed transition"
              title="الصفحة السابقة"
            >
              <ChevronRight size={16} />
            </button>
            
            <div className="flex items-center gap-1">
              {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                let pageNum;
                if (totalPages <= 5) {
                  pageNum = i + 1;
                } else if (currentPage <= 3) {
                  pageNum = i + 1;
                } else if (currentPage >= totalPages - 2) {
                  pageNum = totalPages - 4 + i;
                } else {
                  pageNum = currentPage - 2 + i;
                }
                
                return (
                  <button
                    key={pageNum}
                    onClick={() => handlePageChange(pageNum)}
                    className={`px-3 py-1.5 text-sm rounded-lg transition ${
                      currentPage === pageNum
                        ? 'bg-indigo-600 text-white'
                        : 'text-slate-600 hover:bg-slate-100'
                    }`}
                  >
                    {pageNum}
                  </button>
                );
              })}
            </div>
            
            <button
              onClick={() => handlePageChange(currentPage + 1)}
              disabled={currentPage === totalPages}
              className="p-2 text-slate-400 hover:text-slate-600 disabled:opacity-50 disabled:cursor-not-allowed transition"
              title="الصفحة التالية"
            >
              <ChevronLeft size={16} />
            </button>
            <button
              onClick={() => handlePageChange(totalPages)}
              disabled={currentPage === totalPages}
              className="p-2 text-slate-400 hover:text-slate-600 disabled:opacity-50 disabled:cursor-not-allowed transition"
              title="الصفحة الأخيرة"
            >
              <ChevronsUpDown size={16} className="-rotate-90" />
            </button>
          </div>
        </div>
      )}

      {showForm    && <ExpenseForm    onClose={() => setShowForm(false)}    onSuccess={loadExpenses} />}
      {viewExpense && <ExpenseDetails expense={viewExpense} onClose={() => setViewExpense(null)} onDelete={handleDelete} />}
      
      {/* New Modal for Shared Expense Details with Analysis */}
      <ExpenseDetailsModal
        expenseId={selectedExpenseId || 0}
        currentUserId={currentUserId}
        isOpen={selectedExpenseId !== null}
        onClose={() => setSelectedExpenseId(null)}
      />
    </div>
  );
}
