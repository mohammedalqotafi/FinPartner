import api from './api';
import type { Expense, ExpenseFormData } from '../types/expenses.types';

export const expensesService = {
  async getAll(): Promise<Expense[]> {
    const { data } = await api.get('/expenses');
    return data;
  },

  async getById(id: number): Promise<Expense> {
    const { data } = await api.get(`/expenses/${id}`);
    return data;
  },

  async create(data: ExpenseFormData): Promise<Expense> {
    const response = await api.post('/expenses', data);
    return response.data;
  },

  async update(id: number, data: Partial<ExpenseFormData>): Promise<Expense> {
    const response = await api.put(`/expenses/${id}`, data);
    return response.data;
  },

  async delete(id: number): Promise<void> {
    await api.delete(`/expenses/${id}`);
  }
};
