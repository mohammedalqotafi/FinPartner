import type { Transaction, LedgerRow } from '../types';

// ─── ID Formatting ───────────────────────────────────────────────────────────

export function formatTxId(rawId: string): string {
  // If already in TX- format, return as is; else pad a numeric id
  if (rawId.startsWith('TX-')) return rawId;
  const n = parseInt(rawId, 10);
  return isNaN(n) ? rawId : `TX-${String(n).padStart(4, '0')}`;
}

export function generateTxId(): string {
  const n = Date.now() % 100000;
  return `TX-${String(n).padStart(4, '0')}`;
}

// ─── Date / Time Formatting ───────────────────────────────────────────────────

export function formatDatetime(dt: string): string {
  const d = new Date(dt);
  const date = d.toLocaleDateString('ar-EG', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  });
  const time = d.toLocaleTimeString('ar-EG', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  });
  return `${date} ${time}`;
}

export function formatDateOnly(dt: string): string {
  return new Date(dt).toLocaleDateString('ar-EG', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  });
}

export function isoNow(): string {
  return new Date().toISOString();
}

// ─── Currency Formatting ─────────────────────────────────────────────────────

export function formatAmount(amount: number): string {
  return amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ─── Ledger Calculation ───────────────────────────────────────────────────────

/**
 * Builds ledger rows from an opening balance + list of completed transactions.
 * Rule: Each row balance = previous balance + credit - debit
 *   deposit/adjustment → credit (adds to balance)
 *   withdraw/transfer  → debit  (subtracts from balance)
 */
export function buildLedger(
  openingBalance: number,
  transactions: Transaction[]
): LedgerRow[] {
  const rows: LedgerRow[] = [];

  // Opening balance row
  rows.push({ tx: null, debit: 0, credit: 0, running: openingBalance, isOpening: true });

  let running = openingBalance;

  for (const tx of transactions) {
    const isCredit = tx.type === 'deposit' || tx.type === 'adjustment';
    const credit = isCredit ? tx.amount : 0;
    const debit = !isCredit ? tx.amount : 0;

    if (tx.status === 'completed') {
      running += credit - debit;
    }

    rows.push({ tx, debit, credit, running });
  }

  return rows;
}

// ─── Summary Calculations ─────────────────────────────────────────────────────

export function calcSummary(openingBalance: number, transactions: Transaction[]) {
  const completed = transactions.filter((t) => t.status === 'completed');
  const totalDeposits = completed
    .filter((t) => t.type === 'deposit' || t.type === 'adjustment')
    .reduce((s, t) => s + t.amount, 0);
  const totalWithdrawals = completed
    .filter((t) => t.type === 'withdraw' || t.type === 'transfer')
    .reduce((s, t) => s + t.amount, 0);
  const netChange = totalDeposits - totalWithdrawals;
  const currentBalance = openingBalance + netChange;

  return { totalDeposits, totalWithdrawals, netChange, currentBalance };
}

// ─── Type Labels ──────────────────────────────────────────────────────────────

export const TX_TYPE_LABEL: Record<string, string> = {
  deposit: 'إيداع',
  withdraw: 'سحب',
  transfer: 'تحويل',
  adjustment: 'تسوية',
};

export const TX_TYPE_COLOR: Record<string, string> = {
  deposit: 'bg-emerald-100 text-emerald-700',
  withdraw: 'bg-rose-100 text-rose-700',
  transfer: 'bg-blue-100 text-blue-700',
  adjustment: 'bg-slate-100 text-slate-600',
};

export const TX_STATUS_LABEL: Record<string, string> = {
  completed: 'مكتمل',
  pending: 'معلق',
  cancelled: 'ملغي',
};
