import axios from 'axios';
import type { Member, Transaction, TransactionType, TransactionStatus } from '../types';

// ─── Axios Instance ───────────────────────────────────────────────────────────
const http = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

// ─── Response Interceptor: normalize errors ───────────────────────────────────
http.interceptors.response.use(
  (res) => res,
  (error) => {
    const metaMessage = error.response?.data?.error?.message;
    const message =
      error.response?.data?.message ||
      metaMessage ||
      error.response?.data?.errors ||
      'حدث خطأ في الاتصال بالخادم';
    return Promise.reject(new Error(typeof message === 'string' ? message : JSON.stringify(message)));
  }
);

// ─── Types ────────────────────────────────────────────────────────────────────

export interface DashboardData {
  members_count: number;
  total_balance: number;
  total_deposits: number;
  total_withdrawals: number;
  pending_count: number;
  recent_transactions: Transaction[];
}

export interface MemberListMeta {
  total: number;
  total_balance: number;
  positive_count: number;
  negative_count: number;
}

export interface TransactionMeta {
  total: number;
  total_deposits: number;
  total_withdrawals: number;
  pending_count: number;
  current_page: number;
  last_page: number;
}

// ─── Normalizers: Laravel snake_case → React camelCase ────────────────────────

function normalizeMember(raw: any): Member {
  return {
    id:             String(raw.id),
    name:           raw.name,
    email:          raw.email ?? undefined,
    phone:          raw.phone ?? undefined,
    balance:        Number(raw.balance ?? 0),
    openingBalance: Number(raw.openingBalance ?? raw.opening_balance ?? 0),
    joinDate:       raw.joinDate ?? raw.join_date ?? '',
  };
}

function normalizeTransaction(raw: any): Transaction {
  return {
    id:         raw.id,                   // already TX-0001 format
    memberId:   String(raw.memberId ?? raw.member_id),
    memberName: raw.memberName ?? raw.member_name ?? '',
    type:       raw.type as TransactionType,
    amount:     Number(raw.amount),
    datetime:   raw.datetime ?? raw.transaction_at ?? '',
    note:       raw.note ?? '',
    status:     raw.status as TransactionStatus,
  };
}

// ─── Members API ──────────────────────────────────────────────────────────────

export const membersApi = {
  getAll: async (): Promise<{ members: Member[]; meta: MemberListMeta }> => {
    const res = await http.get('/members');
    return {
      members: res.data.data.map(normalizeMember),
      meta: res.data.meta,
    };
  },

  getOne: async (id: string): Promise<{ member: Member; ledger: any }> => {
    const res = await http.get(`/members/${id}`);
    return {
      member: normalizeMember(res.data.data),
      ledger: res.data.data,
    };
  },

  create: async (data: {
    name: string;
    email?: string;
    phone?: string;
    opening_balance?: number;
  }): Promise<Member> => {
    const res = await http.post('/members', data);
    return normalizeMember(res.data.data);
  },

  update: async (id: string, data: Partial<{ name: string; email: string; phone: string }>): Promise<Member> => {
    const res = await http.put(`/members/${id}`, data);
    return normalizeMember(res.data.data);
  },

  delete: async (id: string): Promise<void> => {
    await http.delete(`/members/${id}`);
  },

  sendWhatsApp: async (id: string, data: { message: string }): Promise<{ message: string; data: any }> => {
    const res = await http.post(`/members/${id}/whatsapp`, data);
    return res.data;
  },
};

// ─── Transactions API ─────────────────────────────────────────────────────────

export interface TransactionFilters {
  member_id?: string;
  type?: string;
  status?: string;
  from_date?: string;
  to_date?: string;
  search?: string;
  per_page?: number;
}

export const transactionsApi = {
  getAll: async (filters: TransactionFilters = {}): Promise<{ transactions: Transaction[]; meta: TransactionMeta }> => {
    const res = await http.get('/transactions', { params: filters });
    return {
      transactions: res.data.data.map(normalizeTransaction),
      meta: res.data.meta,
    };
  },

  create: async (data: {
    member_id: string;
    type: TransactionType;
    amount: number;
    note?: string;
    status?: TransactionStatus;
    transaction_at?: string;
  }): Promise<Transaction> => {
    const res = await http.post('/transactions', data);
    return normalizeTransaction(res.data.data);
  },

  updateStatus: async (reference: string, status: 'completed' | 'cancelled'): Promise<Transaction> => {
    const res = await http.patch(`/transactions/${reference}/status`, { status });
    return normalizeTransaction(res.data.data);
  },
};

// ─── Dashboard API ────────────────────────────────────────────────────────────

export const dashboardApi = {
  get: async (): Promise<DashboardData> => {
    const res = await http.get('/dashboard');
    return {
      ...res.data,
      recent_transactions: (res.data.recent_transactions ?? []).map(normalizeTransaction),
    };
  },
};
