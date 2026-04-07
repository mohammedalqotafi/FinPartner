import { useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AppLayout } from './components/layout/AppLayout';
import { DashboardPage } from './features/dashboard/DashboardPage';
import { TransactionsPage } from './features/transactions/TransactionsPage';
import { DepositsPage } from './features/deposits/DepositsPage';
import { WithdrawalsPage } from './features/withdrawals/WithdrawalsPage';
import { StatementsPage } from './features/statements/StatementsPage';
import { MembersPage } from './features/members/MembersPage';
import { MemberDetailPage } from './features/members/MemberDetailPage';
import { ExpensesPage } from './features/expenses/ExpensesPage';
import { useAppStore } from './store/useAppStore';

function App() {
  const fetchAll   = useAppStore((s) => s.fetchAll);
  const isLoading  = useAppStore((s) => s.isLoading);
  const error      = useAppStore((s) => s.error);

  // ─── جلب البيانات من Laravel عند أول تشغيل ──────────────────────────────
  useEffect(() => {
    fetchAll();
  }, [fetchAll]);

  // ─── شاشة التحميل ────────────────────────────────────────────────────────
  if (isLoading) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <div className="text-center space-y-4">
          <div className="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto" />
          <p className="text-slate-500 font-medium">جاري تحميل البيانات...</p>
        </div>
      </div>
    );
  }

  // ─── شاشة الخطأ ──────────────────────────────────────────────────────────
  if (error) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center p-6">
        <div className="bg-white rounded-2xl p-8 shadow-lg border border-rose-100 max-w-md w-full text-center space-y-4">
          <div className="w-14 h-14 bg-rose-50 rounded-full flex items-center justify-center mx-auto text-2xl">⚠️</div>
          <h2 className="text-lg font-bold text-slate-900">تعذّر الاتصال بالخادم</h2>
          <p className="text-sm text-slate-500">{error}</p>
          <p className="text-xs text-slate-400">تأكد من أن <code className="bg-slate-100 px-1 rounded">php artisan serve</code> يعمل</p>
          <button
            onClick={() => fetchAll()}
            className="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition cursor-pointer"
          >
            إعادة المحاولة
          </button>
        </div>
      </div>
    );
  }

  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<AppLayout />}>
          <Route index element={<DashboardPage />} />
          <Route path="transactions" element={<TransactionsPage />} />
          <Route path="deposits" element={<DepositsPage />} />
          <Route path="withdrawals" element={<WithdrawalsPage />} />
          <Route path="statements" element={<StatementsPage />} />
          <Route path="members" element={<MembersPage />} />
          <Route path="members/:id" element={<MemberDetailPage />} />
          <Route path="expenses" element={<ExpensesPage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

export default App;
