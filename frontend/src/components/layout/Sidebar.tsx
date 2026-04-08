import { NavLink } from 'react-router-dom';
import {
  LayoutDashboard,
  ArrowDownToLine,
  ArrowUpFromLine,
  Users,
  FileText,
  ArrowLeftRight,
  Wallet,
} from 'lucide-react';

const navGroups = [
  {
    label: 'عام',
    items: [
      { to: '/', icon: LayoutDashboard, label: 'لوحة التحكم' },
    ],
  },
  {
    label: 'المعاملات المالية',
    items: [
      { to: '/deposits',     icon: ArrowDownToLine,  label: 'الإيداعات'    },
      { to: '/withdrawals',  icon: ArrowUpFromLine,  label: 'السحوبات'      },
      { to: '/transactions', icon: ArrowLeftRight,    label: 'كل العمليات'  },
      { to: '/expenses',     icon: Wallet,           label: 'المصروفات'    },
    ],
  },
  {
    label: 'التقارير',
    items: [
      { to: '/statements', icon: FileText, label: 'كشف الحساب' },
    ],
  },
  {
    label: 'الإدارة',
    items: [
      { to: '/members', icon: Users, label: 'الأعضاء' },
    ],
  },
];

export function Sidebar() {
  return (
    <aside className="w-64 min-h-screen bg-slate-900 text-white flex flex-col shrink-0 print:hidden">
      {/* Logo */}
      <div className="p-6 border-b border-slate-800">
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 rounded-xl bg-indigo-500 flex items-center justify-center shrink-0">
            <Wallet size={20} />
          </div>
          <div>
            <h1 className="font-bold text-lg leading-tight">FinPartner</h1>
            <p className="text-slate-400 text-xs">إدارة مالية الفريق</p>
          </div>
        </div>
      </div>

      {/* Nav */}
      <nav className="flex-1 p-4 space-y-6 overflow-y-auto">
        {navGroups.map((group) => (
          <div key={group.label}>
            <p className="text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2 px-3">
              {group.label}
            </p>
            <div className="space-y-1">
              {group.items.map(({ to, icon: Icon, label }) => (
                <NavLink
                  key={to}
                  to={to}
                  end={to === '/'}
                  className={({ isActive }) =>
                    `flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all ${
                      isActive
                        ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/40'
                        : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                    }`
                  }
                >
                  <Icon size={17} />
                  {label}
                </NavLink>
              ))}
            </div>
          </div>
        ))}
      </nav>

      {/* Footer */}
      <div className="p-4 border-t border-slate-800">
        <div className="flex items-center gap-3 px-3 py-2">
          <div className="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-xs font-bold shrink-0">
            م
          </div>
          <div>
            <p className="text-sm font-bold">المدير</p>
            <p className="text-xs text-slate-400">مسؤول النظام</p>
          </div>
        </div>
      </div>
    </aside>
  );
}
