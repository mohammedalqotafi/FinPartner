/**
 * Unit tests for expensesService
 * Tests all API calls and error handling (Requirements: 11.1, 11.2, 11.3)
 */
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import MockAdapter from 'axios-mock-adapter';
import api, { ApiError } from './api';
import { expensesService } from './expenses.service';

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const mockExpense = {
  id: 1,
  reference: 'EXP-0001',
  expense_type: 'shared',
  affected_member_id: null,
  category: 'غيارات',
  amount: '100.00',
  payer_id: 2,
  payment_method: 'cash',
  description: 'وصف تجريبي',
  expense_datetime: '2024-11-08T10:35:00.000000Z',
  created_at: '2024-11-08T10:35:00.000000Z',
  updated_at: '2024-11-08T10:35:00.000000Z',
  payer: { id: 2, name: 'أحمد' },
  affected_member: null,
  splits: [
    { id: 1, expense_id: 1, member_id: 2, amount: '50.00' },
    { id: 2, expense_id: 1, member_id: 3, amount: '50.00' },
  ],
};

const mockExpense2 = {
  ...mockExpense,
  id: 2,
  reference: 'EXP-0002',
  expense_type: 'operational',
  amount: '200.00',
  splits: [],
};

// ─── Setup ────────────────────────────────────────────────────────────────────

let mock: MockAdapter;

beforeEach(() => {
  mock = new MockAdapter(api);
});

afterEach(() => {
  mock.restore();
});

// ─── getAll tests (Requirements: 11.2) ───────────────────────────────────────

describe('expensesService.getAll', () => {
  it('returns a normalized array of expenses', async () => {
    mock.onGet('/expenses').reply(200, { data: [mockExpense, mockExpense2] });

    const expenses = await expensesService.getAll();

    expect(Array.isArray(expenses)).toBe(true);
    expect(expenses).toHaveLength(2);
    expect(expenses[0].reference).toBe('EXP-0001');
    expect(expenses[1].reference).toBe('EXP-0002');
  });

  it('normalizes amount as string', async () => {
    mock.onGet('/expenses').reply(200, { data: [mockExpense] });

    const expenses = await expensesService.getAll();

    expect(typeof expenses[0].amount).toBe('string');
    expect(expenses[0].amount).toBe('100.00');
  });

  it('includes splits array', async () => {
    mock.onGet('/expenses').reply(200, { data: [mockExpense] });

    const expenses = await expensesService.getAll();

    expect(Array.isArray(expenses[0].splits)).toBe(true);
    expect(expenses[0].splits).toHaveLength(2);
  });

  it('handles flat array response (no data wrapper)', async () => {
    mock.onGet('/expenses').reply(200, [mockExpense, mockExpense2]);

    const expenses = await expensesService.getAll();

    expect(expenses).toHaveLength(2);
  });

  it('throws ApiError on server error (500)', async () => {
    mock.onGet('/expenses').reply(500, { message: 'حدث خطأ داخلي في الخادم' });

    await expect(expensesService.getAll()).rejects.toBeInstanceOf(ApiError);
  });
});

// ─── getById tests (Requirements: 11.3, 11.7) ────────────────────────────────

describe('expensesService.getById', () => {
  it('returns a single normalized expense', async () => {
    mock.onGet('/expenses/1').reply(200, { data: mockExpense });

    const expense = await expensesService.getById(1);

    expect(expense.id).toBe(1);
    expect(expense.reference).toBe('EXP-0001');
    expect(expense.expense_type).toBe('shared');
  });

  it('includes payer relationship', async () => {
    mock.onGet('/expenses/1').reply(200, { data: mockExpense });

    const expense = await expensesService.getById(1);

    expect(expense.payer).not.toBeNull();
    expect(expense.payer?.name).toBe('أحمد');
  });

  it('includes splits relationship', async () => {
    mock.onGet('/expenses/1').reply(200, { data: mockExpense });

    const expense = await expensesService.getById(1);

    expect(expense.splits).toHaveLength(2);
    expect(expense.splits![0].amount).toBe('50.00');
  });

  it('throws ApiError with status 404 for missing expense', async () => {
    mock.onGet('/expenses/999').reply(404, { message: 'العنصر المطلوب غير موجود' });

    try {
      await expensesService.getById(999);
      expect.fail('should have thrown');
    } catch (err) {
      expect(err).toBeInstanceOf(ApiError);
      expect((err as ApiError).status).toBe(404);
    }
  });
});

// ─── create tests (Requirements: 11.1) ───────────────────────────────────────

describe('expensesService.create', () => {
  it('posts data and returns normalized expense', async () => {
    mock.onPost('/expenses').reply(201, { data: mockExpense });

    const payload = {
      expense_type: 'shared' as const,
      category: 'غيارات',
      amount: 100,
      payment_method: 'cash' as const,
      expense_datetime: '2024-11-08T10:35:00',
      split_type: 'equal' as const,
      members: [{ id: 2 }, { id: 3 }],
    };

    const expense = await expensesService.create(payload);

    expect(expense.id).toBe(1);
    expect(expense.reference).toBe('EXP-0001');
    expect(expense.expense_type).toBe('shared');
  });

  it('throws ApiError with validation errors on 422', async () => {
    mock.onPost('/expenses').reply(422, {
      message: 'البيانات المدخلة غير صحيحة',
      errors: { amount: ['المبلغ يجب أن يكون أكبر من 0.01'] },
    });

    try {
      await expensesService.create({
        expense_type: 'shared',
        category: '',
        amount: 0,
        payment_method: 'cash',
        expense_datetime: '',
      });
      expect.fail('should have thrown');
    } catch (err) {
      expect(err).toBeInstanceOf(ApiError);
      expect((err as ApiError).status).toBe(422);
      expect((err as ApiError).errors).toHaveProperty('amount');
    }
  });
});

// ─── delete tests (Requirements: 11.5) ───────────────────────────────────────

describe('expensesService.delete', () => {
  it('returns success message on deletion', async () => {
    mock.onDelete('/expenses/1').reply(200, { message: 'تم حذف المصروف بنجاح' });

    const result = await expensesService.delete(1);

    expect(result.message).toBe('تم حذف المصروف بنجاح');
  });

  it('throws ApiError with status 404 for missing expense', async () => {
    mock.onDelete('/expenses/999').reply(404, { message: 'العنصر المطلوب غير موجود' });

    try {
      await expensesService.delete(999);
      expect.fail('should have thrown');
    } catch (err) {
      expect(err).toBeInstanceOf(ApiError);
      expect((err as ApiError).status).toBe(404);
    }
  });
});
