import { Outlet } from 'react-router-dom';
import { Wallet } from 'lucide-react';

export function MainLayout() {
  return (
    <div className="min-h-screen font-sans p-4 sm:p-6 lg:p-8 text-slate-800 bg-slate-50">
      <div className="max-w-7xl mx-auto space-y-8">
        <header className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-3xl font-extrabold text-slate-900 tracking-tight">إدارة الديون</h1>
            <p className="text-slate-500 mt-1">تتبع مستحقاتك وديونك مع أعضاء الفريق.</p>
          </div>
          <div className="h-12 w-12 rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-200">
            <Wallet size={24} />
          </div>
        </header>
        
        <main>
          <Outlet />
        </main>
      </div>
    </div>
  );
}
