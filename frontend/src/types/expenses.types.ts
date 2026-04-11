export type ExpenseType = 'shared' | 'operational' | 'personal';
export type SplitType = 'equal' | 'manual';
export type PaymentMethod = 'cash' | 'transfer';

// ─── Member stub used inside expense relationships ────────────────────────────
export interface ExpenseMember {
  id: number;
  name: string;
  email?: string | null;
  phone?: string | null;
  balance?: string | null;
}

// ─── Expense Split (حصة عضو من مصروف مشترك) ─────────────────────────────────
export interface ExpenseSplit {
  id: number;
  expense_id: number;
  member_id: number;
  /** Decimal string from backend (e.g. "33.33") */
  amount: string;
  created_at?: string;
  updated_at?: string;
  member?: ExpenseMember;
}

// ─── Expense (المصروف الكامل مع العلاقات) ────────────────────────────────────
export interface Expense {
  id: number;
  reference: string;
  expense_type: ExpenseType;
  affected_member_id?: number | null;
  category: string;
  /** Decimal string from backend (e.g. "100.00") */
  amount: string;
  payer_id?: number | null;
  payment_method: PaymentMethod;
  description?: string | null;
  /** ISO datetime string (e.g. "2024-11-08T10:35:00.000000Z") */
  expense_datetime: string;
  created_at: string;
  updated_at: string;

  // Eager-loaded relationships (Requirements: 11.7)
  payer?: ExpenseMember | null;
  affected_member?: ExpenseMember | null;
  splits?: ExpenseSplit[];
}

// ─── Form payload sent to POST /api/expenses ─────────────────────────────────
export interface ExpenseFormData {
  expense_type: ExpenseType;
  category: string;
  amount: number;
  payment_method: PaymentMethod;
  expense_datetime: string;
  payer_id?: number | null;
  description?: string | null;

  // Personal expense
  affected_member_id?: number | null;

  // Shared expense
  split_type?: SplitType;
  members?: ExpenseSplitInput[];
}

// ─── Per-member split input ───────────────────────────────────────────────────
export interface ExpenseSplitInput {
  id: number | string;
  /** Required only for manual split_type */
  amount?: number;
}

// ─── API list/detail response wrappers ───────────────────────────────────────
export interface ExpenseListResponse {
  data: Expense[];
}

export interface ExpenseDetailResponse {
  data: Expense;
}

// ─── Delete response ──────────────────────────────────────────────────────────
export interface ExpenseDeleteResponse {
  message: string;
}

// ─── Expense Details with Smart Analysis ─────────────────────────────────────
export interface ExpenseDetailWithAnalysis {
  expense: {
    id: number;
    reference: string;
    expense_type: ExpenseType;
    category: string;
    total_amount: number;
    payment_method: PaymentMethod;
    description?: string | null;
    expense_datetime: string;
    created_at: string;
    payer: ExpenseMember | null;
    affected_member: ExpenseMember | null;
  };
  splits: Array<{
    id: number;
    member: ExpenseMember;
    amount: number;
  }>;
  analysis?: {
    is_payer: boolean;
    your_share?: number;
    you_paid?: number;
    others_owe_you?: number;
    you_owe?: number;
    paid_to?: string;
    paid_to_id?: number | null;
    net_position: number;
  };
}
