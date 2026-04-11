import { useEffect, useMemo, useState } from 'react';
import { X, MessageCircle, Send } from 'lucide-react';
import { membersApi } from '../../services/api';
import type { Member } from '../../types';

interface WhatsAppModalProps {
  member: Member;
  onClose: () => void;
}

export function WhatsAppModal({ member, onClose }: WhatsAppModalProps) {
  const MAX_MESSAGE_LENGTH = 1000;

  const defaultMessage = useMemo(() => {
    const balance = member.balance.toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });

    return [
      `مرحباً ${member.name}`,
      'هذه رسالة من FinPartner.',
      `رصيدك الحالي: ${balance} ر.س`,
    ].join('\n');
  }, [member.balance, member.name]);

  const [message, setMessage] = useState(defaultMessage);
  const [isSending, setIsSending] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const remainingChars = MAX_MESSAGE_LENGTH - message.length;

  useEffect(() => {
    setMessage(defaultMessage);
    setError('');
    setSuccess('');
  }, [defaultMessage, member.id]);

  const handleSend = async () => {
    if (!message.trim()) {
      setError('اكتب نص الرسالة أولاً قبل الإرسال');
      return;
    }

    setIsSending(true);
    setError('');
    setSuccess('');

    try {
      await membersApi.sendWhatsApp(member.id, { message });
      setSuccess('تم إرسال الرسالة بنجاح');
    } catch (err: any) {
      setError(err.message || 'فشل إرسال الرسالة');
    } finally {
      setIsSending(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-sm p-4">
      <div className="w-full max-w-xl rounded-3xl bg-white shadow-2xl overflow-hidden">
        <div className="flex items-start justify-between gap-4 border-b border-slate-100 bg-gradient-to-r from-emerald-50 to-white px-6 py-5">
          <div className="flex items-center gap-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-200">
              <MessageCircle size={22} />
            </div>
            <div>
              <h2 className="text-lg font-extrabold text-slate-900">إرسال واتساب للعميل</h2>
              <p className="text-sm text-slate-500">{member.name}{member.phone ? ` · ${member.phone}` : ' · لا يوجد رقم هاتف'}</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-slate-500 hover:bg-slate-100 hover:text-slate-700"
          >
            <X size={18} />
          </button>
        </div>

        <div className="space-y-4 p-6">
          <div>
            <label className="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500">رقم الهاتف</label>
            <input
              value={member.phone ?? ''}
              readOnly
              className="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 outline-none"
            />
          </div>

          <div>
            <div className="mb-2 flex items-center justify-between">
              <label className="block text-xs font-bold uppercase tracking-wider text-slate-500">نص الرسالة</label>
              <span className={`text-xs font-medium ${remainingChars < 80 ? 'text-rose-500' : 'text-slate-400'}`}>
                {remainingChars} حرف متبق
              </span>
            </div>
            <textarea
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              rows={7}
              maxLength={MAX_MESSAGE_LENGTH}
              className="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-700 outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
            />
            <p className="mt-2 text-xs text-slate-400">
              في وضع الاختبار من Meta، الإرسال يعمل فقط للأرقام المضافة في قائمة الاختبار.
            </p>
          </div>

          {error && (
            <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
              {error}
            </div>
          )}

          {success && (
            <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
              {success}
            </div>
          )}

          <div className="flex gap-3 pt-2">
            <button
              type="button"
              onClick={handleSend}
              disabled={isSending || !member.phone || !message.trim()}
              className="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300"
            >
              <Send size={16} />
              {isSending ? 'جاري الإرسال...' : 'إرسال الرسالة'}
            </button>
            <button
              type="button"
              onClick={onClose}
              className="rounded-2xl bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-200"
            >
              إغلاق
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}