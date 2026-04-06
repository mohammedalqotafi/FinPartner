import { create } from 'zustand';
import type { Member, Transaction, TransactionType, TransactionStatus } from '../types';
import { membersApi, transactionsApi } from '../services/api';
import { isoNow } from '../utils/finance';

interface AppState {
  // ─── Data ──────────────────────────────────────────────────────────────────
  members: Member[];
  transactions: Transaction[];

  // ─── UI State ──────────────────────────────────────────────────────────────
  isLoading: boolean;
  error: string | null;

  // ─── Data Fetching ─────────────────────────────────────────────────────────
  fetchAll: () => Promise<void>;

  // ─── Members ───────────────────────────────────────────────────────────────
  addMember: (data: Omit<Member, 'id' | 'balance' | 'joinDate'>) => Promise<void>;
  updateMember: (id: string, data: Partial<Member>) => Promise<void>;
  deleteMember: (id: string) => Promise<void>;

  // ─── Transactions ──────────────────────────────────────────────────────────
  addTransaction: (data: {
    memberId: string;
    type: TransactionType;
    amount: number;
    note?: string;
    status?: TransactionStatus;
    datetime?: string;
  }) => Promise<void>;

  updateTransactionStatus: (id: string, status: TransactionStatus) => Promise<void>;
}

export const useAppStore = create<AppState>((set, get) => ({
  members: [],
  transactions: [],
  isLoading: false,
  error: null,

  // ─── Fetch All Data from Laravel API ───────────────────────────────────────
  fetchAll: async () => {
    set({ isLoading: true, error: null });
    try {
      const [membersRes, txRes] = await Promise.all([
        membersApi.getAll(),
        transactionsApi.getAll({ per_page: 200 }),
      ]);
      set({
        members: membersRes.members,
        transactions: txRes.transactions,
        isLoading: false,
      });
    } catch (err: any) {
      set({ error: err.message, isLoading: false });
    }
  },

  // ─── Members ───────────────────────────────────────────────────────────────

  addMember: async (data) => {
    const newMember = await membersApi.create({
      name: data.name,
      email: data.email,
      phone: data.phone,
      opening_balance: data.openingBalance,
    });
    set((state) => ({ members: [...state.members, newMember] }));
  },

  updateMember: async (id, data) => {
    const updated = await membersApi.update(id, {
      name: data.name,
      email: data.email,
      phone: data.phone,
    });
    set((state) => ({
      members: state.members.map((m) => (m.id === id ? updated : m)),
    }));
  },

  deleteMember: async (id) => {
    await membersApi.delete(id);
    set((state) => ({
      members: state.members.filter((m) => m.id !== id),
      transactions: state.transactions.filter((t) => t.memberId !== id),
    }));
  },

  // ─── Transactions ──────────────────────────────────────────────────────────

  addTransaction: async (data) => {
    const newTx = await transactionsApi.create({
      member_id: data.memberId,
      type: data.type,
      amount: data.amount,
      note: data.note,
      status: data.status ?? 'completed',
      transaction_at: data.datetime ?? isoNow(),
    });

    // تحديث العملية محلياً + تحديث الرصيد عبر إعادة جلب الأعضاء
    set((state) => ({ transactions: [newTx, ...state.transactions] }));

    // إعادة جلب الأعضاء لتحديث الأرصدة من الخادم
    try {
      const res = await membersApi.getAll();
      set({ members: res.members });
    } catch (_) {}
  },

  updateTransactionStatus: async (id, status) => {
    const updated = await transactionsApi.updateStatus(id, status as 'completed' | 'cancelled');

    set((state) => ({
      transactions: state.transactions.map((t) => (t.id === id ? updated : t)),
    }));

    // إعادة جلب الأعضاء لتحديث الأرصدة
    try {
      const res = await membersApi.getAll();
      set({ members: res.members });
    } catch (_) {}
  },
}));
