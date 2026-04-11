import api, { ApiError } from './api';
import type {
  Expense,
  ExpenseFormData,
  ExpenseListResponse,
  ExpenseDetailResponse,
  ExpenseDeleteResponse,
  ExpenseDetailWithAnalysis,
} from '../types/expenses.types';

// ─── Types for Pagination ─────────────────────────────────────────────────────
export interface PaginatedExpenseResponse {
  data: Expense[];
  meta: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    has_more_pages: boolean;
  };
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
}

export interface ExpenseQueryParams {
  page?: number;
  per_page?: number;
  all?: boolean;
}

// ─── Normalizer: ensure amount fields are strings (backend returns decimal strings) ──
function normalizeExpense(raw: any): Expense {
  return {
    id: raw.id,
    reference: raw.reference,
    expense_type: raw.expense_type,
    affected_member_id: raw.affected_member_id ?? null,
    category: raw.category,
    amount: String(raw.amount),
    payer_id: raw.payer_id ?? null,
    payment_method: raw.payment_method,
    description: raw.description ?? null,
    expense_datetime: raw.expense_datetime,
    created_at: raw.created_at,
    updated_at: raw.updated_at,
    payer: raw.payer ?? null,
    affected_member: raw.affected_member ?? null,
    splits: raw.splits ?? [],
  };
}

// ─── Expenses Service ─────────────────────────────────────────────────────────
export const expensesService = {
  /**
   * GET /api/expenses
   * Returns expenses with pagination support (Requirements: 11.2, 15.4)
   */
  async getAll(params?: ExpenseQueryParams): Promise<Expense[]> {
    const queryParams = new URLSearchParams();
    
    if (params?.page) queryParams.set('page', params.page.toString());
    if (params?.per_page) queryParams.set('per_page', params.per_page.toString());
    if (params?.all) queryParams.set('all', 'true');
    
    const url = `/expenses${queryParams.toString() ? '?' + queryParams.toString() : ''}`;
    const res = await api.get<ExpenseListResponse | PaginatedExpenseResponse>(url);
    
    // Handle both paginated and non-paginated responses
    const items = Array.isArray(res.data) ? res.data : (res.data as any).data ?? [];
    return items.map(normalizeExpense);
  },

  /**
   * GET /api/expenses with pagination
   * Returns paginated expenses (Requirements: 15.4)
   */
  async getPaginated(params?: ExpenseQueryParams): Promise<PaginatedExpenseResponse> {
    const queryParams = new URLSearchParams();
    
    if (params?.page) queryParams.set('page', params.page.toString());
    if (params?.per_page) queryParams.set('per_page', params.per_page.toString());
    
    const url = `/expenses${queryParams.toString() ? '?' + queryParams.toString() : ''}`;
    const res = await api.get<PaginatedExpenseResponse>(url);
    
    return {
      data: res.data.data.map(normalizeExpense),
      meta: res.data.meta,
      links: res.data.links
    };
  },

  /**
   * GET /api/expenses/{id}
   * Returns a single expense with relationships (Requirements: 11.3, 11.7)
   */
  async getById(id: number): Promise<Expense> {
    const res = await api.get<ExpenseDetailResponse>(`/expenses/${id}`);
    const raw = (res.data as any).data ?? res.data;
    return normalizeExpense(raw);
  },

  /**
   * GET /api/expenses/{id}?current_user_id={userId}
   * Returns expense details with smart analysis for the current user
   */
  async getByIdWithAnalysis(id: number, currentUserId?: number): Promise<ExpenseDetailWithAnalysis> {
    const url = currentUserId 
      ? `/expenses/${id}?current_user_id=${currentUserId}`
      : `/expenses/${id}`;
    const res = await api.get<ExpenseDetailWithAnalysis>(url);
    return res.data;
  },

  /**
   * POST /api/expenses
   * Creates a new expense (Requirements: 11.1)
   */
  async create(data: ExpenseFormData): Promise<Expense> {
    const res = await api.post<ExpenseDetailResponse>('/expenses', data);
    const raw = (res.data as any).data ?? res.data;
    return normalizeExpense(raw);
  },

  /**
   * DELETE /api/expenses/{id}
   * Deletes an expense and recalculates affected member balances (Requirements: 11.5)
   */
  async delete(id: number): Promise<ExpenseDeleteResponse> {
    const res = await api.delete<ExpenseDeleteResponse>(`/expenses/${id}`);
    return res.data;
  },
};

// Re-export ApiError so consumers can catch typed errors
export { ApiError };
