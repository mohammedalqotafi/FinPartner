/**
 * @vitest-environment jsdom
 *
 * Component tests for ExpenseForm
 * Tests: validation (13.2), shared split UI (13.3), live preview (13.4, 13.5)
 */
import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ExpenseForm } from './ExpenseForm';

// ─── Mock zustand store ───────────────────────────────────────────────────────
const mockMembers = [
  { id: '1', name: 'أحمد', balance: 0, openingBalance: 0, joinDate: '2024-01-01' },
  { id: '2', name: 'محمد', balance: 0, openingBalance: 0, joinDate: '2024-01-01' },
  { id: '3', name: 'سارة', balance: 0, openingBalance: 0, joinDate: '2024-01-01' },
];

vi.mock('../../../store/useAppStore', () => ({
  useAppStore: (selector: any) => selector({ members: mockMembers }),
}));

// ─── Mock expenses service ────────────────────────────────────────────────────
const mockCreate = vi.fn();
vi.mock('../../../services/expenses.service', () => ({
  expensesService: { create: (...args: any[]) => mockCreate(...args) },
  ApiError: class ApiError extends Error {
    status: number;
    errors: Record<string, string[]>;
    constructor(message: string, status: number, errors?: Record<string, string[]>) {
      super(message);
      this.status = status;
      this.errors = errors || {};
    }
  },
}));

// ─── Helpers ──────────────────────────────────────────────────────────────────
function renderForm(props: { onClose?: () => void; onSuccess?: () => void } = {}) {
  const onClose = props.onClose ?? vi.fn();
  const onSuccess = props.onSuccess ?? vi.fn();
  const result = render(<ExpenseForm onClose={onClose} onSuccess={onSuccess} />);
  return { ...result, onClose, onSuccess };
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('ExpenseForm — Validation (Requirements: 13.2)', () => {
  beforeEach(() => {
    mockCreate.mockReset();
  });

  it('renders the form with required fields', () => {
    renderForm();
    expect(screen.getByText('إضافة مصروف')).toBeInTheDocument();
    expect(screen.getByText(/إجمالي المبلغ/)).toBeInTheDocument();
    expect(screen.getByText(/التصنيف/)).toBeInTheDocument();
    expect(screen.getByText(/تاريخ ووقت المعاملة/)).toBeInTheDocument();
  });

  it('shows validation error when amount is missing on submit', async () => {
    renderForm();
    const submitBtn = screen.getByText('اعتماد وحفظ المصروف');

    // Type 0 (invalid amount) and submit
    const amountInput = screen.getByPlaceholderText('0.00');
    await userEvent.clear(amountInput);
    await userEvent.type(amountInput, '0');
    await userEvent.click(submitBtn);

    await waitFor(() => {
      // Should show error that amount must be greater than 0.01
      expect(screen.getByText(/المبلغ يجب أن يكون أكبر من 0.01/)).toBeInTheDocument();
    });
  });

  it('shows validation error when category is empty on submit', async () => {
    renderForm();
    const submitBtn = screen.getByText('اعتماد وحفظ المصروف');

    // Fill amount but leave category empty
    const amountInput = screen.getByPlaceholderText('0.00');
    await userEvent.clear(amountInput);
    await userEvent.type(amountInput, '100');
    await userEvent.click(submitBtn);

    await waitFor(() => {
      expect(screen.getByText('التصنيف مطلوب')).toBeInTheDocument();
    });
  });

  it('shows validation error for personal expense without affected member', async () => {
    renderForm();

    // Switch to personal type
    const typeSelect = screen.getByRole('combobox', { name: /نوع المصروف/ });
    await userEvent.selectOptions(typeSelect, 'personal');

    // Fill required fields
    const amountInput = screen.getByPlaceholderText('0.00');
    await userEvent.type(amountInput, '50');
    const categoryInput = screen.getByPlaceholderText(/غيارات/);
    await userEvent.type(categoryInput, 'رواتب');

    const submitBtn = screen.getByText('اعتماد وحفظ المصروف');
    await userEvent.click(submitBtn);

    await waitFor(() => {
      expect(
        screen.getByText('يجب تحديد العضو المتأثر للمصروف الشخصي')
      ).toBeInTheDocument();
    });
  });
});

describe('ExpenseForm — Shared Split UI (Requirements: 13.3)', () => {
  beforeEach(() => {
    mockCreate.mockReset();
  });

  it('shows member selection buttons for shared expense type', () => {
    renderForm();
    // Default is 'shared', so member buttons should be visible
    // Use getByRole to specifically target the button elements (not the options in select)
    expect(screen.getByRole('button', { name: 'أحمد' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'محمد' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'سارة' })).toBeInTheDocument();
  });

  it('toggles member selection on click', async () => {
    renderForm();
    const ahmedBtn = screen.getByRole('button', { name: 'أحمد' });

    // Initially not selected (white background)
    expect(ahmedBtn).not.toHaveClass('bg-indigo-600');

    await userEvent.click(ahmedBtn);
    expect(ahmedBtn).toHaveClass('bg-indigo-600');

    await userEvent.click(ahmedBtn);
    expect(ahmedBtn).not.toHaveClass('bg-indigo-600');
  });

  it('shows equal/manual split toggle buttons', () => {
    renderForm();
    expect(screen.getByRole('button', { name: 'بالتساوي' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'يدوي' })).toBeInTheDocument();
  });

  it('switches to manual split mode', async () => {
    renderForm();
    const manualBtn = screen.getByRole('button', { name: 'يدوي' });
    await userEvent.click(manualBtn);
    expect(manualBtn).toHaveClass('bg-indigo-600');
  });

  it('hides shared split UI for operational expense type', async () => {
    renderForm();
    const typeSelect = screen.getByRole('combobox', { name: /نوع المصروف/ });
    await userEvent.selectOptions(typeSelect, 'operational');

    expect(screen.queryByText('تقسيم المصروف التشاركي')).not.toBeInTheDocument();
  });
});

describe('ExpenseForm — Live Preview (Requirements: 13.4, 13.5)', () => {
  beforeEach(() => {
    mockCreate.mockReset();
  });

  it('shows split preview when members are selected with equal split', async () => {
    renderForm();

    // Set amount
    const amountInput = screen.getByPlaceholderText('0.00');
    await userEvent.clear(amountInput);
    await userEvent.type(amountInput, '100');

    // Select two members
    await userEvent.click(screen.getByRole('button', { name: 'أحمد' }));
    await userEvent.click(screen.getByRole('button', { name: 'محمد' }));

    // Preview section should appear
    await waitFor(() => {
      expect(screen.getByText(/معاينة التوزيع/)).toBeInTheDocument();
    });
  });

  it('shows math verified badge when equal split is correct', async () => {
    renderForm();

    const amountInput = screen.getByPlaceholderText('0.00');
    await userEvent.clear(amountInput);
    await userEvent.type(amountInput, '100');

    await userEvent.click(screen.getByRole('button', { name: 'أحمد' }));
    await userEvent.click(screen.getByRole('button', { name: 'محمد' }));

    await waitFor(() => {
      expect(screen.getByRole('status', { name: 'split-verified' })).toBeInTheDocument();
    });
  });

  it('shows unverified badge when manual split does not match total', async () => {
    renderForm();

    const amountInput = screen.getByPlaceholderText('0.00');
    await userEvent.clear(amountInput);
    await userEvent.type(amountInput, '100');

    // Switch to manual
    await userEvent.click(screen.getByRole('button', { name: 'يدوي' }));

    // Select a member
    await userEvent.click(screen.getByRole('button', { name: 'أحمد' }));

    // Manual amount is 0 by default → doesn't match 100
    await waitFor(() => {
      expect(screen.getByRole('status', { name: 'split-unverified' })).toBeInTheDocument();
    });
  });

  it('shows verified badge when manual split matches total', async () => {
    renderForm();

    const amountInput = screen.getByPlaceholderText('0.00');
    await userEvent.clear(amountInput);
    await userEvent.type(amountInput, '100');

    // Switch to manual
    await userEvent.click(screen.getByRole('button', { name: 'يدوي' }));

    // Select one member
    await userEvent.click(screen.getByRole('button', { name: 'أحمد' }));

    // Enter matching manual amount
    await waitFor(() => {
      const manualInputs = screen.getAllByPlaceholderText('0.00');
      // The manual amount input (not the main amount input)
      const manualInput = manualInputs.find((el) => el.closest('.divide-y'));
      if (manualInput) {
        fireEvent.change(manualInput, { target: { value: '100' } });
      }
    });

    await waitFor(() => {
      expect(screen.getByRole('status', { name: 'split-verified' })).toBeInTheDocument();
    });
  });
});

describe('ExpenseForm — Submission (Requirements: 13.2, 13.3, 13.4)', () => {
  beforeEach(() => {
    mockCreate.mockReset();
  });

  it('calls expensesService.create with correct data on valid submission', async () => {
    mockCreate.mockResolvedValueOnce({ id: 1, reference: 'EXP-0001' });
    const onClose = vi.fn();
    const onSuccess = vi.fn();
    render(<ExpenseForm onClose={onClose} onSuccess={onSuccess} />);

    // Fill operational expense (simplest — no members needed)
    const typeSelect = screen.getByRole('combobox', { name: /نوع المصروف/ });
    await userEvent.selectOptions(typeSelect, 'operational');

    const amountInput = screen.getByPlaceholderText('0.00');
    await userEvent.clear(amountInput);
    await userEvent.type(amountInput, '200');

    const categoryInput = screen.getByPlaceholderText(/غيارات/);
    await userEvent.type(categoryInput, 'رواتب');

    await userEvent.click(screen.getByText('اعتماد وحفظ المصروف'));

    await waitFor(() => {
      expect(mockCreate).toHaveBeenCalledOnce();
      expect(mockCreate).toHaveBeenCalledWith(
        expect.objectContaining({
          expense_type: 'operational',
          amount: 200,
          category: 'رواتب',
        })
      );
      expect(onSuccess).toHaveBeenCalled();
      expect(onClose).toHaveBeenCalled();
    });
  });

  it('displays backend field errors on 422 response', async () => {
    const { ApiError } = await import('../../../services/expenses.service');
    mockCreate.mockRejectedValueOnce(
      new ApiError('البيانات المدخلة غير صحيحة', 422, {
        amount: ['المبلغ يجب أن يكون أكبر من 0.01'],
      })
    );

    renderForm();

    const typeSelect = screen.getByRole('combobox', { name: /نوع المصروف/ });
    await userEvent.selectOptions(typeSelect, 'operational');

    const amountInput = screen.getByPlaceholderText('0.00');
    await userEvent.clear(amountInput);
    await userEvent.type(amountInput, '50');

    const categoryInput = screen.getByPlaceholderText(/غيارات/);
    await userEvent.type(categoryInput, 'اختبار');

    await userEvent.click(screen.getByText('اعتماد وحفظ المصروف'));

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
      expect(screen.getByText(/يرجى تصحيح الأخطاء أدناه/)).toBeInTheDocument();
    });
  });

  it('displays generic backend error on 500 response', async () => {
    const { ApiError } = await import('../../../services/expenses.service');
    mockCreate.mockRejectedValueOnce(
      new ApiError('حدث خطأ داخلي في الخادم', 500)
    );

    renderForm();

    const typeSelect = screen.getByRole('combobox', { name: /نوع المصروف/ });
    await userEvent.selectOptions(typeSelect, 'operational');

    const amountInput = screen.getByPlaceholderText('0.00');
    await userEvent.clear(amountInput);
    await userEvent.type(amountInput, '50');

    const categoryInput = screen.getByPlaceholderText(/غيارات/);
    await userEvent.type(categoryInput, 'اختبار');

    await userEvent.click(screen.getByText('اعتماد وحفظ المصروف'));

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
      expect(screen.getByText('حدث خطأ داخلي في الخادم')).toBeInTheDocument();
    });
  });

  it('calls onClose when cancel button is clicked', async () => {
    const { onClose } = renderForm();
    await userEvent.click(screen.getByText('إلغاء'));
    expect(onClose).toHaveBeenCalled();
  });
});
