// ─── Debt Types ──────────────────────────────────────────────────────────────

export interface Debt {
  debtor_id: number;
  debtor_name: string;
  creditor_id: number;
  creditor_name: string;
  amount: string;
}

export interface DebtSummary {
  total_owed: string;
  total_owing: string;
  net_position: string;
}

export interface MemberDebtSummary {
  member: {
    id: number;
    name: string;
  };
  summary: DebtSummary;
  debts_owed_to_member: Debt[];
  debts_owed_by_member: Debt[];
}

export interface DebtResponse {
  data: Debt[];
  meta: {
    total_debts: string;
    active_debtors: number;
    active_creditors: number;
    debt_relationships: number;
  };
}

export interface SimplifiedDebt {
  debtor: number;
  creditor: number;
  amount: number;
}

export interface DebtMatrix {
  matrix: (number | null)[][];
  members: Array<{
    id: number;
    name: string;
  }>;
}