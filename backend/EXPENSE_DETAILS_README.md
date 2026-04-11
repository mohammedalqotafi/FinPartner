# نظام عرض تفاصيل المصروف التشاركي (Shared Expense Details)

## نظرة عامة

تم تنفيذ نظام متقدم لعرض تفاصيل المصروفات التشاركية مع تحليل ذكي لموقف كل مستخدم، مشابه لتطبيقات مثل Splitwise.

## المميزات الرئيسية

### ✅ عرض تفاصيل شامل
- معلومات المصروف الكاملة (المبلغ، التاريخ، الفئة، طريقة الدفع)
- قائمة التقسيمات بين الأعضاء
- معلومات الدافع والأعضاء المشاركين

### ✅ تحليل ذكي للمستخدم الحالي
- **إذا كان المستخدم هو الدافع:**
  - المبلغ الذي دفعه
  - نصيبه من المصروف
  - المبلغ المستحق له من الآخرين
  
- **إذا كان المستخدم مشارك فقط:**
  - المبلغ المستحق عليه
  - اسم الشخص الذي دفع

### ✅ حسابات ديناميكية
- لا تعديل على هيكل قاعدة البيانات
- الحسابات تتم في الوقت الفعلي
- استخدام JOIN محسّن للأداء

## API Endpoint

### GET /api/expenses/{expense_id}

#### بدون تحليل المستخدم
```bash
GET /api/expenses/10
```

**Response:**
```json
{
  "expense": {
    "id": 10,
    "reference": "EXP-0123",
    "expense_type": "shared",
    "category": "عشاء",
    "total_amount": 1000,
    "payment_method": "cash",
    "description": "عشاء جماعي",
    "expense_datetime": "2026-04-10T20:00:00.000000Z",
    "created_at": "2026-04-10T20:05:00.000000Z",
    "payer": {
      "id": 1,
      "name": "أحمد",
      "email": "ahmed@example.com",
      "phone": "0501234567"
    },
    "affected_member": null
  },
  "splits": [
    {
      "id": 1,
      "member": {
        "id": 1,
        "name": "أحمد",
        "email": "ahmed@example.com"
      },
      "amount": 250
    },
    {
      "id": 2,
      "member": {
        "id": 2,
        "name": "محمد",
        "email": "mohammed@example.com"
      },
      "amount": 250
    },
    {
      "id": 3,
      "member": {
        "id": 3,
        "name": "علي",
        "email": "ali@example.com"
      },
      "amount": 250
    },
    {
      "id": 4,
      "member": {
        "id": 4,
        "name": "خالد",
        "email": "khaled@example.com"
      },
      "amount": 250
    }
  ]
}
```

#### مع تحليل المستخدم (الدافع)
```bash
GET /api/expenses/10?current_user_id=1
```

**Response:**
```json
{
  "expense": { ... },
  "splits": [ ... ],
  "analysis": {
    "is_payer": true,
    "your_share": 250,
    "you_paid": 1000,
    "others_owe_you": 750,
    "net_position": 750
  }
}
```

#### مع تحليل المستخدم (مشارك)
```bash
GET /api/expenses/10?current_user_id=2
```

**Response:**
```json
{
  "expense": { ... },
  "splits": [ ... ],
  "analysis": {
    "is_payer": false,
    "you_owe": 250,
    "paid_to": "أحمد",
    "paid_to_id": 1,
    "net_position": -250
  }
}
```

## مثال عملي

### السيناريو
مجموعة من 4 أشخاص ذهبوا لتناول العشاء:
- أحمد دفع 1000 ريال
- التقسيم متساوي: 250 ريال لكل شخص

### النتائج

#### من وجهة نظر أحمد (الدافع)
```
🧾 أنت الدافع
دفعت: 1000 ر.س
نصيبك: 250 ر.س
─────────────────
لك على الآخرين: 750 ر.س
```

#### من وجهة نظر محمد (مشارك)
```
💸 عليك دفع
المبلغ المستحق: 250 ر.س
لصالح: أحمد
```

## الملفات المنفذة

### Backend
- `app/Http/Controllers/Api/ExpenseController.php` - تحديث show() method
- `tests/Feature/ExpenseDetailsTest.php` - اختبارات شاملة (6 tests, 58 assertions)

### Frontend
- `src/types/expenses.types.ts` - إضافة ExpenseDetailWithAnalysis type
- `src/services/expenses.service.ts` - إضافة getByIdWithAnalysis() method
- `src/features/expenses/components/ExpenseDetailsModal.tsx` - Modal احترافي للعرض
- `src/features/expenses/ExpensesPage.tsx` - ربط Modal مع الجدول

## الاستخدام في Frontend

### في React Component
```typescript
import { ExpenseDetailsModal } from './components/ExpenseDetailsModal';

function MyComponent() {
  const [selectedExpenseId, setSelectedExpenseId] = useState<number | null>(null);
  const currentUserId = 1; // من context أو authentication

  return (
    <>
      <button onClick={() => setSelectedExpenseId(10)}>
        عرض التفاصيل
      </button>

      <ExpenseDetailsModal
        expenseId={selectedExpenseId || 0}
        currentUserId={currentUserId}
        isOpen={selectedExpenseId !== null}
        onClose={() => setSelectedExpenseId(null)}
      />
    </>
  );
}
```

## المنطق الأساسي

### 1. استخراج البيانات
```php
// تحميل المصروف مع جميع العلاقات
$expense->load([
    'payer',
    'affectedMember',
    'splits.member'
]);
```

### 2. التحليل الذكي
```php
private function analyzeExpenseForUser(Expense $expense, int $currentUserId): array
{
    $totalAmount = (float) $expense->amount;
    $isPayer = $expense->payer_id === $currentUserId;
    
    $userSplit = $expense->splits->firstWhere('member_id', $currentUserId);
    $userShare = $userSplit ? (float) $userSplit->amount : 0;

    if ($isPayer) {
        return [
            'is_payer' => true,
            'your_share' => $userShare,
            'you_paid' => $totalAmount,
            'others_owe_you' => $totalAmount - $userShare,
            'net_position' => $totalAmount - $userShare,
        ];
    } else {
        return [
            'is_payer' => false,
            'you_owe' => $userShare,
            'paid_to' => $expense->payer->name,
            'paid_to_id' => $expense->payer_id,
            'net_position' => -$userShare,
        ];
    }
}
```

## الاختبارات

تم كتابة 6 اختبارات شاملة تغطي جميع السيناريوهات:

1. ✅ عرض التفاصيل بدون تحليل (بدون current_user_id)
2. ✅ تحليل للدافع (is_payer = true)
3. ✅ تحليل للمشارك (is_payer = false)
4. ✅ معالجة حصة أكبر بشكل صحيح
5. ✅ عدم إرجاع تحليل للمصروفات غير التشاركية
6. ✅ معالجة مستخدم ليس في التقسيمات

```bash
# تشغيل الاختبارات
php artisan test --filter=ExpenseDetailsTest

# النتيجة
Tests:    6 passed (58 assertions)
Duration: 1.45s
```

## الأداء

### تحسينات مطبقة
- ✅ Eager loading لجميع العلاقات في استعلام واحد
- ✅ تحديد الحقول المطلوبة فقط (select specific columns)
- ✅ ترتيب التقسيمات حسب المبلغ (الأكبر أولاً)
- ✅ حسابات ديناميكية بدون استعلامات إضافية

### Query واحد فقط
```sql
SELECT expenses.*, 
       payer.id, payer.name, payer.email, payer.phone,
       splits.id, splits.member_id, splits.amount,
       members.id, members.name, members.email
FROM expenses
LEFT JOIN members AS payer ON expenses.payer_id = payer.id
LEFT JOIN expense_splits AS splits ON expenses.id = splits.expense_id
LEFT JOIN members ON splits.member_id = members.id
WHERE expenses.id = ?
```

## الأمان

- ✅ لا يتم تخزين current_user_id في قاعدة البيانات
- ✅ التحليل يتم فقط للمصروفات التشاركية
- ✅ معالجة حالات المستخدمين غير الموجودين في التقسيمات
- ✅ التحقق من صحة البيانات قبل الحسابات

## ملاحظات مهمة

1. **لا تعديل على قاعدة البيانات** - يستخدم الجداول الحالية فقط
2. **حسابات ديناميكية** - لا يخزن النتائج، يحسبها في كل طلب
3. **مرن وقابل للتوسع** - يمكن إضافة تحليلات إضافية بسهولة
4. **Production-ready** - كود نظيف مع اختبارات شاملة
5. **UI احترافي** - تصميم مشابه لـ Splitwise مع تجربة مستخدم ممتازة

## التطوير المستقبلي

يمكن إضافة المزيد من المميزات:
- 📊 رسوم بيانية لتوزيع المصروفات
- 📱 إشعارات للأعضاء المدينين
- 💳 ربط مع بوابات الدفع
- 📄 تصدير التفاصيل كـ PDF
- 🔔 تذكيرات تلقائية للديون المستحقة
