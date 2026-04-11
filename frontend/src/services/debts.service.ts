import api, { ApiError } from './api';
import type {
  Debt,
  DebtResponse,
  MemberDebtSummary,
  SimplifiedDebt,
  DebtMatrix,
} from '../types/debts.types';

// ─── Debts Service ────────────────────────────────────────────────────────────
export const debtsService = {
  /**
   * GET /api/debts
   * Returns all debts between members with netting applied
   */
  async getAll(): Promise<DebtResponse> {
    const res = await api.get<DebtResponse>('/debts');
    return res.data;
  },

  /**
   * GET /api/debts/simplified
   * Returns debts in simplified format (without member names)
   */
  async getSimplified(): Promise<SimplifiedDebt[]> {
    const res = await api.get<SimplifiedDebt[]>('/debts/simplified');
    return res.data;
  },

  /**
   * GET /api/debts/member/{memberId}
   * Returns debt summary for a specific member
   */
  async getMemberSummary(memberId: number): Promise<MemberDebtSummary> {
    const res = await api.get<MemberDebtSummary>(`/debts/member/${memberId}`);
    return res.data;
  },

  /**
   * GET /api/debts/matrix
   * Returns debt matrix for all members
   */
  async getMatrix(): Promise<DebtMatrix> {
    const res = await api.get<DebtMatrix>('/debts/matrix');
    return res.data;
  },
};

// Re-export ApiError so consumers can catch typed errors
export { ApiError };