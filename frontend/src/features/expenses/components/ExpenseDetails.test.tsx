/**
 * @vitest-environment jsdom
 *
 * Component tests for ExpenseDetails
 * Tests: display details (11.7), display splits (11.7)
 * Requirements: 11.7
 */
import React from 'react';
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ExpenseDetails } from './ExpenseDetails';
import type { Expense } from '../../../types/expenses.types';

// ─── Test Data ────────────────────────────────────────────────────────────────

const mockSharedExpense: Expense = {
  id: 1,
  reference: 'EXP-0001',
  expense_type: 'shared',
  category: 'غيارات',
  amount: '100.00',
  payer_id: 1,
  payment_method: 'cash',
  description: 'قطع غيار للسيارة',
  expense_datetime: '2024-04-10T10:00:00.000000Z',
  created_at: '2024-04-10T10:00:00.000000Z',
  updated_at: '2024-04-10T10:00:00.000000Z',
  payer: {
    id: 1,
    name: 'أحمد',
    email: 'ahmed@example.com',
    phone: '0501234567',
    balance: '500.00',
  },
  splits: [
    {
      id: 1,
      expense_id: 1,
      member_id: 1,
      amount: '33.34',
      member: {
        id: 1,
        name: 'أحمد',
      },
    },
    {
      id: 2,
      expense_id: 1,
      member_id: 2,
      amount: '33.33',
      member: {
        id: 2,
        name: 'محمد',
      },
    },
    {
      id: 3,
      expense_id: 1,
      member_id: 3,
      amount: '33.33',
      member: {
        id: 3,
        name: 'سارة',
      },
    },
  ],
};

const mockPersonalExpense: Expense = {
  id: 2,
  reference: 'EXP-0002',
  expense_type: 'personal',
  category: 'رواتب',
  amount: '500.00',
  payer_id: null,
  payment_method: 'transfer',
  description: 'راتب شهري',
  expense_datetime: '2024-04-10T12:00:00.000000Z',
  created_at: '2024-04-10T12:00:00.000000Z',
  updated_at: '2024-04-10T12:00:00.000000Z',
  affected_member_id: 2,
  affected_member: {
    id: 2,
    name: 'محمد',
  },
};

const mockOperationalExpense: Expense = {
  id: 3,
  reference: 'EXP-0003',
  expense_type: 'operational',
  category: 'إيجار',
  amount: '2000.00',
  payer_id: null,
  payment_method: 'transfer',
  description: null,
  expense_datetime: '2024-04-10T14:00:00.000000Z',
  created_at: '2024-04-10T14:00:00.000000Z',
  updated_at: '2024-04-10T14:00:00.000000Z',
};

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('ExpenseDetails — Display Details (Requirements: 11.7)', () => {
  it('renders expense reference', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockSharedExpense} onClose={onClose} />);
    
    expect(screen.getByText('EXP-0001')).toBeInTheDocument();
  });

  it('renders expense amount', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockSharedExpense} onClose={onClose} />);
    
    expect(screen.getByText('100.00 ر.س')).toBeInTheDocument();
  });

  it('renders expense type', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockSharedExpense} onClose={onClose} />);
    
    expect(screen.getByText('shared')).toBeInTheDocument();
  });

  it('renders category and description', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockSharedExpense} onClose={onClose} />);
    
    expect(screen.getByText(/غيارات/)).toBeInTheDocument();
    expect(screen.getByText(/قطع غيار للسيارة/)).toBeInTheDocument();
  });

  it('renders "بدون بيان" when description is null', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockOperationalExpense} onClose={onClose} />);
    
    expect(screen.getByText(/بدون بيان/)).toBeInTheDocument();
  });

  it('renders expense datetime', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockSharedExpense} onClose={onClose} />);
    
    // The date is formatted using toLocaleString, so we just check it exists
    const dateElement = screen.getByText(/التاريخ/).parentElement;
    expect(dateElement).toBeInTheDocument();
  });

  it('renders payer name when payer exists', () => {
    const onClose = vi.fn();
    const { container } = render(<ExpenseDetails expense={mockSharedExpense} onClose={onClose} />);
    
    // Check payer section exists
    expect(screen.getByText(/الجهة الدافعة/)).toBeInTheDocument();
    
    // Find the payer name in the indigo badge
    const payerBadge = container.querySelector('.text-indigo-700.bg-indigo-50');
    expect(payerBadge).toHaveTextContent('أحمد');
  });

  it('renders "خزانة النظام" when payer is null', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockPersonalExpense} onClose={onClose} />);
    
    expect(screen.getByText(/خزانة النظام/)).toBeInTheDocument();
  });

  it('renders affected member for personal expense', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockPersonalExpense} onClose={onClose} />);
    
    expect(screen.getByText(/العضو المتأثر/)).toBeInTheDocument();
    expect(screen.getByText('محمد')).toBeInTheDocument();
  });

  it('does not render affected member section for non-personal expense', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockSharedExpense} onClose={onClose} />);
    
    expect(screen.queryByText(/العضو المتأثر/)).not.toBeInTheDocument();
  });

  it('calls onClose when close button is clicked', async () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockSharedExpense} onClose={onClose} />);
    
    const closeButton = screen.getByRole('button');
    await userEvent.click(closeButton);
    
    expect(onClose).toHaveBeenCalledOnce();
  });
});

describe('ExpenseDetails — Display Splits (Requirements: 11.7)', () => {
  it('renders splits section for shared expense', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockSharedExpense} onClose={onClose} />);
    
    expect(screen.getByText(/تفصيل التقسيم/)).toBeInTheDocument();
  });

  it('renders all splits with member names and amounts', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockSharedExpense} onClose={onClose} />);
    
    // Check splits section exists
    expect(screen.getByText(/تفصيل التقسيم/)).toBeInTheDocument();
    
    // Check all three members are displayed using getAllByText for names that appear multiple times
    const ahmedElements = screen.getAllByText(/أحمد/);
    expect(ahmedElements.length).toBeGreaterThan(0);
    
    expect(screen.getByText('محمد')).toBeInTheDocument();
    expect(screen.getByText('سارة')).toBeInTheDocument();
    
    // Check amounts are displayed
    expect(screen.getByText('33.34 ر.س')).toBeInTheDocument();
    expect(screen.getAllByText('33.33 ر.س')).toHaveLength(2);
  });

  it('marks payer in splits list', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockSharedExpense} onClose={onClose} />);
    
    expect(screen.getByText('(Payer)')).toBeInTheDocument();
  });

  it('shows net effect calculation when payer is participant', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockSharedExpense} onClose={onClose} />);
    
    expect(screen.getByText(/النتيجة المحاسبية للدافع/)).toBeInTheDocument();
    // Net effect = 100.00 - 33.34 = 66.66
    expect(screen.getByText(/\+66\.66 ر\.س/)).toBeInTheDocument();
  });

  it('does not render splits section for personal expense', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockPersonalExpense} onClose={onClose} />);
    
    expect(screen.queryByText(/تفصيل التقسيم/)).not.toBeInTheDocument();
  });

  it('does not render splits section for operational expense', () => {
    const onClose = vi.fn();
    render(<ExpenseDetails expense={mockOperationalExpense} onClose={onClose} />);
    
    expect(screen.queryByText(/تفصيل التقسيم/)).not.toBeInTheDocument();
  });

  it('handles empty splits array gracefully', () => {
    const onClose = vi.fn();
    const expenseWithNoSplits: Expense = {
      ...mockSharedExpense,
      splits: [],
    };
    render(<ExpenseDetails expense={expenseWithNoSplits} onClose={onClose} />);
    
    expect(screen.queryByText(/تفصيل التقسيم/)).not.toBeInTheDocument();
  });

  it('renders fallback member ID when member name is missing', () => {
    const onClose = vi.fn();
    const expenseWithMissingMemberName: Expense = {
      ...mockSharedExpense,
      splits: [
        {
          id: 1,
          expense_id: 1,
          member_id: 99,
          amount: '100.00',
        },
      ],
    };
    render(<ExpenseDetails expense={expenseWithMissingMemberName} onClose={onClose} />);
    
    expect(screen.getByText(/عضو #99/)).toBeInTheDocument();
  });
});
