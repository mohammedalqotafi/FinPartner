# Who Owes Who - نظام حساب الديون بين الأعضاء

## نظرة عامة

تم تنفيذ نظام "Who Owes Who" لحساب الديون بين الأعضاء بناءً على المصروفات المشتركة مع تطبيق خوارزمية التبسيط (Netting) لإزالة الديون المتبادلة.

## المنطق الأساسي

### 1. استخراج العلاقات
```sql
SELECT 
    expense_splits.member_id as debtor_id,
    expenses.payer_id as creditor_id,
    SUM(expense_splits.amount) as amount
FROM expense_splits
JOIN expenses ON expense_splits.expense_id = expenses.id
WHERE expenses.payer_id IS NOT NULL  -- تجاهل مدفوعات الخزانة
AND expense_splits.member_id != expenses.payer_id  -- تجاهل الدفع للنفس
GROUP BY expense_splits.member_id, expenses.payer_id
```

### 2. تطبيق Netting
- إذا كان A مدين لـ B بمبلغ X و B مدين لـ A بمبلغ Y
- النتيجة: الأكبر يستحق الفرق من الأصغر
- مثال: A يدين 100 لـ B، B يدين 60 لـ A → A يدين 40 لـ B

### 3. النتيجة النهائية
قائمة مبسطة من الديون بدون تكرار أو ديون متبادلة.

## الملفات المنفذة

### Backend
- `app/Services/DebtCalculationService.php` - الخدمة الأساسية لحساب الديون
- `app/Http/Controllers/Api/DebtController.php` - Controller للـ API endpoints
- `routes/api.php` - إضافة routes للديون
- `tests/Feature/DebtCalculationTest.php` - اختبارات الخدمة
- `tests/Feature/DebtApiTest.php` - اختبارات الـ API

### Frontend
- `src/types/debts.types.ts` - أنواع البيانات للديون
- `src/services/debts.service.ts` - خدمة التعامل مع API الديون
- `src/features/debts/DebtsPage.tsx` - صفحة عرض الديون

## API Endpoints

### GET /api/debts
عرض جميع الديون مع الإحصائيات
```json
{
  "data": [
    {
      "debtor_id": 2,
      "debtor_name": "Bob",
      "creditor_id": 1,
      "creditor_name": "Alice",
      "amount": "100.00"
    }
  ],
  "meta": {
    "total_debts": "100.00",
    "active_debtors": 1,
    "active_creditors": 1,
    "debt_relationships": 1
  }
}
```

### GET /api/debts/simplified
الشكل المبسط المطلوب
```json
[
  {
    "debtor": 2,
    "creditor": 1,
    "amount": 100
  }
]
```

### GET /api/debts/member/{id}
ملخص ديون عضو معين
```json
{
  "member": {"id": 1, "name": "Alice"},
  "summary": {
    "total_owed": "100.00",
    "total_owing": "0.00", 
    "net_position": "100.00"
  },
  "debts_owed_to_member": [...],
  "debts_owed_by_member": [...]
}
```

### GET /api/debts/matrix
مصفوفة الديون بين جميع الأعضاء

## مثال عملي

### السيناريو:
1. Alice دفعت 300 ريال عشاء للثلاثة (Alice, Bob, Charlie) - 100 لكل واحد
2. Bob دفع 200 ريال تاكسي لـ Alice و Bob - 100 لكل واحد

### الحسابات:
- Bob يدين لـ Alice: 100 ريال (من العشاء)
- Alice تدين لـ Bob: 100 ريال (من التاكسي)
- بعد Netting: متعادلان (0 ريال)
- Charlie يدين لـ Alice: 100 ريال (من العشاء فقط)

### النتيجة النهائية:
```json
[
  {
    "debtor": 3,
    "creditor": 1, 
    "amount": 100
  }
]
```

## المميزات

### ✅ الأداء
- استخدام GROUP BY لتجميع البيانات في قاعدة البيانات
- تحميل أسماء الأعضاء مرة واحدة فقط
- حساب ديناميكي بدون تخزين إضافي

### ✅ الدقة
- تطبيق Netting صحيح لتجنب الديون المتبادلة
- تجاهل مدفوعات الخزانة والدفع للنفس
- دقة عشرية كاملة (decimal 2 places)

### ✅ المرونة
- عدة endpoints لاحتياجات مختلفة
- إحصائيات شاملة
- معالجة أخطاء متقدمة

### ✅ الاختبارات
- 18 اختبار شامل (97 assertions)
- تغطية جميع السيناريوهات المعقدة
- اختبارات API و Service منفصلة

## الاستخدام

```php
// في الكود
$debtService = new DebtCalculationService();
$debts = $debtService->calculateDebts();
$memberSummary = $debtService->getMemberDebtSummary($memberId);
```

```bash
# اختبار الـ API
curl -X GET "http://localhost:8000/api/debts" -H "Accept: application/json"
```

## ملاحظات مهمة

1. **لا تعديل على هيكل قاعدة البيانات** - يستخدم الجداول الحالية فقط
2. **حساب ديناميكي** - لا يخزن النتائج، يحسبها في كل طلب
3. **Netting تلقائي** - يبسط الديون المتبادلة تلقائياً
4. **أداء محسّن** - استخدام SQL GROUP BY بدلاً من حلقات PHP
5. **مقاوم للأخطاء** - معالجة شاملة للحالات الاستثنائية