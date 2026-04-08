export type ExpenseType = 'shared' | 'operational' | 'personal';
export type SplitType = 'equal' | 'manual';

export interface ExpenseSplit {
  id: number;
  expense_id: number;
  member_id: number;
  amount: string;
  member?: any; // Assuming member object is attached
}

export interface Expense {
  id: number;
  reference: string;
  expense_type: ExpenseType;
  affected_member_id?: number | null;
  category: string;
  amount: string; // From backend decimal
  payer_id?: number | null;
  payment_method: string;
  description?: string | null;
  expense_datetime: string;
  created_at: string;
  updated_at: string;
  
  payer?: any;
  affected_member?: any;
  splits?: ExpenseSplit[];
}

export interface ExpenseFormData {
  reference: string;
  expense_type: ExpenseType;
  affected_member_id?: number | null;
  category: string;
  amount: number;
  payer_id?: number | null;
  payment_method: string;
  description?: string;
  expense_datetime: string;
  
  split_type?: SplitType;
  members?: { id: string | number; amount?: number }[];
}
