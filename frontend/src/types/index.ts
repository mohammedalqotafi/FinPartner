export type TransactionType = 'deposit' | 'withdraw' | 'transfer' | 'adjustment';
export type TransactionStatus = 'completed' | 'pending' | 'cancelled';

export interface Member {
  id: string;
  name: string;
  email?: string;
  phone?: string;
  balance: number;        // calculated running total
  openingBalance: number; // starting balance before any transactions
  joinDate: string;
}

export interface Transaction {
  id: string;            // TX-0001 format
  memberId: string;
  memberName: string;
  type: TransactionType;
  amount: number;
  datetime: string;      // ISO format: 2024-11-08T10:35:00
  note: string;
  status: TransactionStatus;
  reference?: string;
}

// Calculated row for ledger display
export interface LedgerRow {
  tx: Transaction | null; // null = opening balance row
  debit: number;
  credit: number;
  running: number;
  isOpening?: boolean;
}

// Re-export debt types
export * from './debts.types';
export * from './expenses.types';
