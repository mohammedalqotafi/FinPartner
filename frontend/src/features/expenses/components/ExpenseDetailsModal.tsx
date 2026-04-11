import React, { useState, useEffect } from 'react';
import { X, Receipt, Users, DollarSign, Calendar, CreditCard, FileText, TrendingUp, TrendingDown } from 'lucide-react';
import { expensesService } from '../../../services/expenses.service';
import type { ExpenseDetailWithAnalysis } from '../../../types/expenses.types';

interface ExpenseDetailsModalProps {
  expenseId: number;
  currentUserId?: number;
  isOpen: boolean;
  onClose: () => void;
}

export function ExpenseDetailsModal({ expenseId, currentUserId, isOpen, onClose }: ExpenseDetailsModalProps) {
  const [details, setDetails] = useState<ExpenseDetailWithAnalysis | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (isOpen && expenseId) {
      loadDetails();
    }
  }, [isOpen, expenseId, currentUserId]);

  const loadDetails = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await expensesService.getByIdWithAnalysis(expenseId, currentUserId);
      setDetails(data);
    } catch (e) {
      console.error(e);
      setError('حدث خطأ أثناء تحميل التفاصيل');
    } finally {
      setIsLoading(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4" onClick={onClose}>
      <div 
        className="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto"
        onClick={(e) => e.stopPropagation()}
        dir="rtl"
      >
        {/* Header */}
        <div className="sticky top-0 bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-6 rounded-t-2xl flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
              <Receipt size={24} />
            </div>
            <div>
              <h2 className="text-2xl font-extrabold">تفاصيل المصروف</h2>
              {details && (
                <p className="text-indigo-100 text-sm mt-0.5">{details.expense.reference}</p>
              )}
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-10 h-10 bg-white/20 hover:bg-white/30 rounded-xl flex items-center justify-center transition"
          >
            <X size={20} />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-6">
          {isLoading && (
            <div className="flex items-center justify-center py-16">
              <div className="flex items-center gap-3 text-slate-600">
                <div className="w-6 h-6 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin" />
                جاري التحميل...
              </div>
            </div>
          )}

          {error && (
            <div className="bg-red-50 border border-red-200 rounded-xl p-4 text-center text-red-700">
              {error}
            </div>
          )}

          {details && !isLoading && (
            <>
              {/* Analysis Section - للمصروفات التشاركية فقط */}
              {details.analysis && (
                <div className={`rounded-2xl p-6 border-2 ${
                  details.analysis.is_payer 
                    ? 'bg-emerald-50 border-emerald-200' 
                    : 'bg-amber-50 border-amber-200'
                }`}>
                  <div className="flex items-center gap-3 mb-4">
                    <div className={`w-12 h-12 rounded-xl flex items-center justify-center ${
                      details.analysis.is_payer ? 'bg-emerald-100' : 'bg-amber-100'
                    }`}>
                      {details.analysis.is_payer ? (
                        <TrendingUp size={24} className="text-emerald-600" />
                      ) : (
                        <TrendingDown size={24} className="text-amber-600" />
                      )}
                    </div>
                    <h3 className="text-xl font-extrabold text-slate-800">
                      {details.analysis.is_payer ? '🧾 أنت الدافع' : '💸 عليك دفع'}
                    </h3>
                  </div>

                  {details.analysis.is_payer ? (
                    <div className="space-y-3">
                      <div className="flex justify-between items-center">
                        <span className="text-slate-600">دفعت:</span>
                        <span className="text-2xl font-extrabold text-emerald-700">
                          {details.analysis.you_paid?.toFixed(2)} ر.س
                        </span>
                      </div>
                      <div className="flex justify-between items-center">
                        <span className="text-slate-600">نصيبك:</span>
                        <span className="text-lg font-bold text-slate-700">
                          {details.analysis.your_share?.toFixed(2)} ر.س
                        </span>
                      </div>
                      <div className="h-px bg-emerald-200 my-2" />
                      <div className="flex justify-between items-center">
                        <span className="text-emerald-700 font-bold">لك على الآخرين:</span>
                        <span className="text-3xl font-extrabold text-emerald-600">
                          {details.analysis.others_owe_you?.toFixed(2)} ر.س
                        </span>
                      </div>
                    </div>
                  ) : (
                    <div className="space-y-3">
                      <div className="flex justify-between items-center">
                        <span className="text-slate-600">المبلغ المستحق:</span>
                        <span className="text-3xl font-extrabold text-amber-700">
                          {details.analysis.you_owe?.toFixed(2)} ر.س
                        </span>
                      </div>
                      <div className="flex justify-between items-center">
                        <span className="text-slate-600">لصالح:</span>
                        <span className="text-lg font-bold text-slate-700">
                          {details.analysis.paid_to}
                        </span>
                      </div>
                    </div>
                  )}
                </div>
              )}

              {/* Expense Info */}
              <div className="bg-slate-50 rounded-2xl p-6 space-y-4">
                <h3 className="text-lg font-extrabold text-slate-800 mb-4">معلومات المصروف</h3>
                
                <div className="grid grid-cols-2 gap-4">
                  <div className="flex items-start gap-3">
                    <div className="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                      <DollarSign size={18} className="text-indigo-600" />
                    </div>
                    <div>
                      <p className="text-xs text-slate-500">المبلغ الإجمالي</p>
                      <p className="text-xl font-extrabold text-slate-800">
                        {details.expense.total_amount.toFixed(2)} ر.س
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start gap-3">
                    <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                      <FileText size={18} className="text-purple-600" />
                    </div>
                    <div>
                      <p className="text-xs text-slate-500">الفئة</p>
                      <p className="text-lg font-bold text-slate-800">{details.expense.category}</p>
                    </div>
                  </div>

                  <div className="flex items-start gap-3">
                    <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                      <Calendar size={18} className="text-blue-600" />
                    </div>
                    <div>
                      <p className="text-xs text-slate-500">التاريخ</p>
                      <p className="text-sm font-bold text-slate-800">
                        {new Date(details.expense.expense_datetime).toLocaleDateString('ar-SA')}
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start gap-3">
                    <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                      <CreditCard size={18} className="text-green-600" />
                    </div>
                    <div>
                      <p className="text-xs text-slate-500">طريقة الدفع</p>
                      <p className="text-sm font-bold text-slate-800">
                        {details.expense.payment_method === 'cash' ? 'نقدي' : 'تحويل'}
                      </p>
                    </div>
                  </div>
                </div>

                {details.expense.payer && (
                  <div className="pt-4 border-t border-slate-200">
                    <p className="text-xs text-slate-500 mb-2">الدافع</p>
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                        <span className="text-sm font-bold text-indigo-600">
                          {details.expense.payer.name.charAt(0)}
                        </span>
                      </div>
                      <div>
                        <p className="font-bold text-slate-800">{details.expense.payer.name}</p>
                        {details.expense.payer.email && (
                          <p className="text-xs text-slate-500">{details.expense.payer.email}</p>
                        )}
                      </div>
                    </div>
                  </div>
                )}

                {details.expense.description && (
                  <div className="pt-4 border-t border-slate-200">
                    <p className="text-xs text-slate-500 mb-2">الوصف</p>
                    <p className="text-sm text-slate-700">{details.expense.description}</p>
                  </div>
                )}
              </div>

              {/* Splits Section */}
              {details.splits.length > 0 && (
                <div className="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                  <div className="bg-slate-700 text-white p-4 flex items-center gap-3">
                    <Users size={20} />
                    <h3 className="text-lg font-extrabold">👥 التقسيم بين الأعضاء</h3>
                  </div>
                  <div className="divide-y divide-slate-100">
                    {details.splits.map((split) => (
                      <div key={split.id} className="p-4 flex items-center justify-between hover:bg-slate-50 transition">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                            <span className="text-sm font-bold text-indigo-600">
                              {split.member.name.charAt(0)}
                            </span>
                          </div>
                          <div>
                            <p className="font-bold text-slate-800">{split.member.name}</p>
                            {split.member.email && (
                              <p className="text-xs text-slate-500">{split.member.email}</p>
                            )}
                          </div>
                        </div>
                        <div className="text-left">
                          <span className="text-xl font-extrabold text-slate-800">
                            {split.amount.toFixed(2)}
                          </span>
                          <span className="text-xs text-slate-400 mr-1">ر.س</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
