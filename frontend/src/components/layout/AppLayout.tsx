import { Outlet } from 'react-router-dom';
import { Sidebar } from './Sidebar';

export function AppLayout() {
  return (
    <div className="flex min-h-screen bg-slate-50 print:bg-white print:block">
      <Sidebar />
      <main className="flex-1 overflow-auto print:overflow-visible print:w-full">
        <Outlet />
      </main>
    </div>
  );
}
