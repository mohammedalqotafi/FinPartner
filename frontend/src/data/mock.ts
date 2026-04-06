import type { Member, Transaction } from '../types';

export const mockMembers: Member[] = [
  { id: '1', name: 'أحمد محمود',  email: 'ahmed@example.com', phone: '0501234567', balance: 4500, openingBalance: 0, joinDate: '2024-01-15' },
  { id: '2', name: 'سارة خالد',   email: 'sara@example.com',  phone: '0557654321', balance: 1200, openingBalance: 0, joinDate: '2024-02-20' },
  { id: '3', name: 'محمد علي',    email: 'moh@example.com',   phone: '0531112233', balance: 800,  openingBalance: 0, joinDate: '2024-03-05' },
  { id: '4', name: 'فاطمة أحمد',  email: 'fat@example.com',   phone: '0543334455', balance: 3800, openingBalance: 500, joinDate: '2024-03-18' },
  { id: '5', name: 'عمر حسن',     email: 'omar@example.com',  phone: '0569998877', balance: 650,  openingBalance: 0, joinDate: '2024-04-01' },
];

export const mockTransactions: Transaction[] = [
  // أحمد محمود (ID:1)
  { id: 'TX-0001', memberId: '1', memberName: 'أحمد محمود', type: 'deposit',    amount: 2000, datetime: '2024-11-01T10:30:00', note: 'إيداع شهري',          status: 'completed' },
  { id: 'TX-0004', memberId: '1', memberName: 'أحمد محمود', type: 'deposit',    amount: 1000, datetime: '2024-11-05T14:00:00', note: 'إيداع إضافي',          status: 'completed' },
  { id: 'TX-0009', memberId: '1', memberName: 'أحمد محمود', type: 'withdraw',   amount: 500,  datetime: '2024-11-14T09:15:00', note: 'سحب قرض',              status: 'completed' },
  { id: 'TX-0014', memberId: '1', memberName: 'أحمد محمود', type: 'deposit',    amount: 1500, datetime: '2024-11-25T16:45:00', note: 'إيداع نهاية الشهر',    status: 'completed' },
  { id: 'TX-0016', memberId: '1', memberName: 'أحمد محمود', type: 'transfer',   amount: 200,  datetime: '2024-11-28T11:20:00', note: 'تحويل لسارة',           status: 'pending'   },
  { id: 'TX-0018', memberId: '1', memberName: 'أحمد محمود', type: 'adjustment', amount: 500,  datetime: '2024-11-30T08:00:00', note: 'تسوية فروق حسابية',    status: 'completed' },

  // سارة خالد (ID:2)
  { id: 'TX-0002', memberId: '2', memberName: 'سارة خالد',  type: 'deposit',    amount: 1500, datetime: '2024-11-02T09:00:00', note: 'مساهمة صندوق',         status: 'completed' },
  { id: 'TX-0007', memberId: '2', memberName: 'سارة خالد',  type: 'withdraw',   amount: 300,  datetime: '2024-11-10T13:30:00', note: 'سحب شخصي',             status: 'completed' },
  { id: 'TX-0011', memberId: '2', memberName: 'سارة خالد',  type: 'deposit',    amount: 500,  datetime: '2024-11-18T10:00:00', note: 'دفعة إضافية',           status: 'completed' },
  { id: 'TX-0017', memberId: '2', memberName: 'سارة خالد',  type: 'transfer',   amount: 100,  datetime: '2024-11-29T14:00:00', note: 'استلام تحويل',          status: 'completed' },

  // محمد علي (ID:3)
  { id: 'TX-0003', memberId: '3', memberName: 'محمد علي',   type: 'withdraw',   amount: 500,  datetime: '2024-11-03T14:20:00', note: 'سحب طارئ',             status: 'completed' },
  { id: 'TX-0008', memberId: '3', memberName: 'محمد علي',   type: 'deposit',    amount: 1300, datetime: '2024-11-12T15:00:00', note: 'إيداع شهري',           status: 'completed' },
  { id: 'TX-0013', memberId: '3', memberName: 'محمد علي',   type: 'deposit',    amount: 200,  datetime: '2024-11-22T08:30:00', note: 'تسوية رصيد',            status: 'pending'   },

  // فاطمة أحمد (ID:4) - لها رصيد افتتاحي 500
  { id: 'TX-0005', memberId: '4', memberName: 'فاطمة أحمد', type: 'deposit',    amount: 3000, datetime: '2024-11-07T11:00:00', note: 'مساهمة ربع سنوية',     status: 'completed' },
  { id: 'TX-0010', memberId: '4', memberName: 'فاطمة أحمد', type: 'withdraw',   amount: 700,  datetime: '2024-11-15T14:30:00', note: 'مصاريف طارئة',         status: 'pending'   },
  { id: 'TX-0015', memberId: '4', memberName: 'فاطمة أحمد', type: 'deposit',    amount: 800,  datetime: '2024-11-28T16:15:00', note: 'مساهمة إضافية',        status: 'completed' },
  { id: 'TX-0019', memberId: '4', memberName: 'فاطمة أحمد', type: 'adjustment', amount: 200,  datetime: '2024-11-30T09:00:00', note: 'تعديل خطأ إدخال',      status: 'completed' },

  // عمر حسن (ID:5)
  { id: 'TX-0006', memberId: '5', memberName: 'عمر حسن',    type: 'deposit',    amount: 800,  datetime: '2024-11-08T10:35:00', note: 'إيداع شهري',           status: 'completed' },
  { id: 'TX-0012', memberId: '5', memberName: 'عمر حسن',    type: 'withdraw',   amount: 150,  datetime: '2024-11-20T12:00:00', note: 'سحب جزئي',             status: 'completed' },
];
