# Design Document - نظام إدارة المصروفات المتقدم

## Overview

نظام إدارة المصروفات المتقدم هو نظام محاسبي متكامل مصمم بعناية ليتكامل بسلاسة مع النظام المالي الحالي (FinPartner). يعتمد التصميم على مبادئ المحاسبة الدقيقة، الأداء العالي، وسهولة الصيانة. النظام يدعم ثلاثة أنواع من المصروفات مع آليات تقسيم متقدمة وحساب رصيد موحد شامل.

### Design Philosophy

1. **Accuracy First**: الدقة المحاسبية هي الأولوية القصوى - لا مجال للخطأ في الكسور العشرية
2. **Atomic Operations**: جميع العمليات المالية تتم بشكل ذري لضمان الاتساق
3. **Unified Ledger**: دفتر أستاذ موحد يجمع المعاملات والمصروفات
4. **Performance Optimized**: تصميم محسّن للأداء مع eager loading و indexing
5. **Maintainable Code**: كود نظيف وموثق بشكل جيد

## Architecture

### System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend (React + TypeScript)             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ ExpensesPage │  │ ExpenseForm  │  │ExpenseDetails│      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ HTTP/JSON (RESTful API)
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Backend (Laravel 11 + PHP 8.2)            │
│  ┌──────────────────────────────────────────────────────┐   │
│  │              API Layer (Routes + Controllers)         │   │
│  │  ┌────────────────────────────────────────────────┐  │   │
│  │  │         ExpenseController                       │  │   │
│  │  │  - index()  - store()  - show()  - destroy()   │  │   │
│  │  └────────────────────────────────────────────────┘  │   │
│  └──────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────┐   │
│  │              Business Logic Layer                     │   │
│  │  ┌────────────────────────────────────────────────┐  │   │
│  │  │         TransactionService                      │  │   │
│  │  │  - recalculateBalance()                         │  │   │
│  │  └────────────────────────────────────────────────┘  │   │
│  └──────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────┐   │
│  │              Data Layer (Models + Eloquent)           │   │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────┐   │   │
│  │  │ Expense  │  │ExpenseSpl│  │  Member          │   │   │
│  │  │  Model   │  │it Model  │  │  Model           │   │   │
│  │  └──────────┘  └──────────┘  └──────────────────┘   │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Database (SQLite/MySQL)                   │
│  ┌──────────┐  ┌──────────────┐  ┌──────────────────┐      │
│  │ expenses │  │expense_splits│  │  members         │      │
│  │  table   │  │    table     │  │  table           │      │
│  └──────────┘  └──────────────┘  └──────────────────┘      │
└─────────────────────────────────────────────────────────────┘
```

### Layer Responsibilities

1. **Frontend Layer**: واجهة المستخدم، التحقق من البيانات، عرض المعاينات
2. **API Layer**: استقبال الطلبات، التحقق من الصلاحيات، إرجاع الاستجابات
3. **Business Logic Layer**: المنطق المحاسبي، حساب التقسيمات، تحديث الأرصدة
4. **Data Layer**: التفاعل مع قاعدة البيانات، العلاقات، الـ Accessors
5. **Database Layer**: تخزين البيانات، الفهارس، القيود



## Components and Interfaces

### Backend Components

#### 1. Expense Model

```php
class Expense extends Model
{
    // Properties
    protected $guarded = ['id'];
    protected $casts = [
        'amount' => 'decimal:2',
        'expense_datetime' => 'datetime'
    ];
    
    // Relationships
    public function payer(): BelongsTo
    public function affectedMember(): BelongsTo
    public function splits(): HasMany
    
    // Methods
    public static function generateReference(): string
}
```

**Responsibilities:**
- تمثيل المصروف في قاعدة البيانات
- إدارة العلاقات مع الأعضاء والتقسيمات
- توليد المرجع الفريد

#### 2. ExpenseSplit Model

```php
class ExpenseSplit extends Model
{
    // Properties
    protected $guarded = ['id'];
    protected $casts = [
        'amount' => 'decimal:2'
    ];
    
    // Relationships
    public function expense(): BelongsTo
    public function member(): BelongsTo
}
```

**Responsibilities:**
- تمثيل حصة عضو من مصروف مشترك
- ربط المصروف بالعضو

#### 3. Member Model (Enhanced)

```php
class Member extends Model
{
    // Existing properties + relationships
    
    // New Relationships
    public function expenseSplits(): HasMany
    public function personalExpenses(): HasMany
    public function paidExpenses(): HasMany
    
    // Enhanced Accessor
    public function getCalculatedBalanceAttribute(): float
    {
        $depositWithdrawNet = $this->net_change;
        $sharedSplits = (float) $this->expenseSplits()->sum('amount');
        $personalExpenses = (float) $this->personalExpenses()->sum('amount');
        $paidContributions = (float) $this->paidExpenses()->sum('amount');
        
        return (float) $this->opening_balance 
            + $depositWithdrawNet 
            - $sharedSplits 
            - $personalExpenses 
            + $paidContributions;
    }
}
```

**Responsibilities:**
- حساب الرصيد الموحد الشامل
- إدارة العلاقات مع المصروفات والتقسيمات

#### 4. ExpenseController

```php
class ExpenseController extends Controller
{
    public function index(): JsonResponse
    public function store(Request $request): JsonResponse
    public function show(Expense $expense): JsonResponse
    public function destroy(Expense $expense): JsonResponse
}
```

**Responsibilities:**
- معالجة طلبات API
- التحقق من البيانات
- إدارة المعاملات الذرية
- إرجاع الاستجابات

#### 5. TransactionService (Enhanced)

```php
class TransactionService
{
    public function recalculateBalance(Member $member): void
    {
        $newBalance = $member->calculated_balance;
        $member->update(['balance' => $newBalance]);
    }
}
```

**Responsibilities:**
- إعادة حساب أرصدة الأعضاء
- ضمان الاتساق بين المعاملات والمصروفات

### Frontend Components

#### 1. ExpensesPage Component

```typescript
interface ExpensesPageProps {}

interface ExpensesPageState {
    expenses: Expense[];
    isLoading: boolean;
    showForm: boolean;
    viewExpense: Expense | null;
    search: string;
    filterType: string;
}
```

**Responsibilities:**
- عرض قائمة المصروفات
- الفلترة والبحث
- عرض الإحصائيات
- إدارة الحالة

#### 2. ExpenseForm Component

```typescript
interface ExpenseFormProps {
    onClose: () => void;
    onSuccess?: () => void;
}

interface ExpenseFormData {
    reference: string;
    expense_type: ExpenseType;
    category: string;
    amount: number;
    payer_id?: number | null;
    payment_method: string;
    description?: string;
    expense_datetime: string;
    affected_member_id?: number | null;
    split_type?: SplitType;
    members?: { id: string | number; amount?: number }[];
}
```

**Responsibilities:**
- إدخال بيانات المصروف
- التحقق من البيانات
- حساب التقسيمات المعاينة
- التحقق الرياضي

#### 3. ExpenseDetails Component

```typescript
interface ExpenseDetailsProps {
    expense: Expense;
    onClose: () => void;
}
```

**Responsibilities:**
- عرض تفاصيل المصروف
- عرض التقسيمات
- عرض معلومات الدافع



## Data Models

### Database Schema

#### expenses Table

```sql
CREATE TABLE expenses (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    reference VARCHAR(255) UNIQUE NOT NULL,
    expense_type ENUM('shared', 'operational', 'personal') NOT NULL,
    affected_member_id BIGINT UNSIGNED NULL,
    category VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payer_id BIGINT UNSIGNED NULL,
    payment_method VARCHAR(255) NOT NULL,
    description TEXT NULL,
    expense_datetime DATETIME NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (affected_member_id) REFERENCES members(id) ON DELETE SET NULL,
    FOREIGN KEY (payer_id) REFERENCES members(id) ON DELETE SET NULL,
    
    INDEX idx_expense_type (expense_type),
    INDEX idx_expense_datetime (expense_datetime),
    INDEX idx_category (category),
    INDEX idx_payer_id (payer_id),
    INDEX idx_affected_member_id (affected_member_id)
);
```

**Field Descriptions:**
- `id`: المعرف الفريد للمصروف
- `reference`: الرقم المرجعي الفريد (EXP-XXXX)
- `expense_type`: نوع المصروف (shared/operational/personal)
- `affected_member_id`: العضو المتأثر (للمصروفات الشخصية فقط)
- `category`: تصنيف المصروف (غيارات، رواتب، إيجار، إلخ)
- `amount`: المبلغ الإجمالي للمصروف
- `payer_id`: العضو الدافع (null = الخزانة)
- `payment_method`: طريقة الدفع (cash/transfer)
- `description`: وصف تفصيلي اختياري
- `expense_datetime`: التاريخ والوقت الفعلي للمصروف

**Design Decisions:**
1. `amount` بصيغة DECIMAL(10,2) لدقة عشرية كاملة
2. `expense_type` كـ ENUM لضمان القيم الصحيحة
3. Foreign keys مع ON DELETE SET NULL للحفاظ على السجلات التاريخية
4. Indexes على الحقول المستخدمة في البحث والفلترة

#### expense_splits Table

```sql
CREATE TABLE expense_splits (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    expense_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    
    INDEX idx_expense_id (expense_id),
    INDEX idx_member_id (member_id),
    UNIQUE KEY unique_expense_member (expense_id, member_id)
);
```

**Field Descriptions:**
- `id`: المعرف الفريد للتقسيم
- `expense_id`: معرف المصروف المرتبط
- `member_id`: معرف العضو المشارك
- `amount`: حصة العضو من المصروف

**Design Decisions:**
1. ON DELETE CASCADE لحذف التقسيمات تلقائياً عند حذف المصروف
2. UNIQUE constraint على (expense_id, member_id) لمنع التكرار
3. Indexes على expense_id و member_id للأداء

#### members Table (Enhanced)

```sql
-- الجدول موجود مسبقاً، لا تعديلات مطلوبة على البنية
-- العلاقات الجديدة تُدار من خلال Eloquent فقط
```

**Enhanced Relationships:**
- `expenseSplits()`: جميع التقسيمات المرتبطة بالعضو
- `personalExpenses()`: المصروفات الشخصية للعضو
- `paidExpenses()`: المصروفات التي دفعها العضو

### Entity Relationship Diagram

```
┌─────────────────────┐
│      members        │
│─────────────────────│
│ id (PK)             │
│ name                │
│ email               │
│ phone               │
│ opening_balance     │
│ balance             │◄──────────┐
│ join_date           │           │
│ created_at          │           │
│ updated_at          │           │
│ deleted_at          │           │
└─────────────────────┘           │
         ▲                        │
         │                        │
         │ payer_id               │ affected_member_id
         │ (nullable)             │ (nullable)
         │                        │
┌────────┴────────────────────────┴────┐
│           expenses                   │
│──────────────────────────────────────│
│ id (PK)                              │
│ reference (UNIQUE)                   │
│ expense_type (ENUM)                  │
│ affected_member_id (FK, nullable)    │
│ category                             │
│ amount                               │
│ payer_id (FK, nullable)              │
│ payment_method                       │
│ description                          │
│ expense_datetime                     │
│ created_at                           │
│ updated_at                           │
└──────────────────────────────────────┘
         │
         │ expense_id
         │ (CASCADE DELETE)
         ▼
┌─────────────────────┐
│  expense_splits     │
│─────────────────────│
│ id (PK)             │
│ expense_id (FK)     │───────┐
│ member_id (FK)      │───────┼──► members.id
│ amount              │       │
│ created_at          │       │
│ updated_at          │       │
└─────────────────────┘       │
                              │
         UNIQUE (expense_id, member_id)
```

### Data Flow

#### Creating a Shared Expense

```
1. User Input → Frontend Validation
2. POST /api/expenses with:
   {
     expense_type: 'shared',
     amount: 100.00,
     members: [{id: 1}, {id: 2}, {id: 3}],
     split_type: 'equal'
   }
3. Backend Validation
4. DB::beginTransaction()
5. Create Expense record
6. Calculate splits using Penny Routing:
   - Base: 100.00 / 3 = 33.33
   - Total assigned: 33.33 * 3 = 99.99
   - Remainder: 0.01
   - SHA-256(reference) % 3 = recipient_index
   - Member at recipient_index gets 33.34
7. Create ExpenseSplit records
8. DB::commit()
9. Recalculate balances for all affected members
10. Return response with relationships
```



## Correctness Properties

A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.

### Property 1: Expense Type Validation

*For any* expense creation request, the system should accept only the three valid expense types (shared, operational, personal) and reject any other values.

**Validates: Requirements 1.1, 10.2**

### Property 2: Required Fields Based on Type

*For any* expense with type 'shared', the system should require a non-empty members list and split_type, and for any expense with type 'personal', the system should require affected_member_id.

**Validates: Requirements 1.2, 1.3**

### Property 3: Equal Split Mathematical Accuracy

*For any* shared expense with equal split type, the sum of all member splits should exactly equal the total expense amount with zero remainder.

**Validates: Requirements 2.1, 2.2**

### Property 4: Penny Routing Determinism

*For any* shared expense with equal split, applying the penny routing algorithm twice with the same reference should produce identical split distributions.

**Validates: Requirements 2.3**

### Property 5: Manual Split Sum Verification

*For any* shared expense with manual split type, the system should accept the expense only if the sum of manual amounts exactly equals the total expense amount.

**Validates: Requirements 2.4, 2.5**

### Property 6: Payer Balance Contribution

*For any* expense with a payer_id, the payer's calculated balance should increase by the expense amount (as they paid on behalf of others or the system).

**Validates: Requirements 3.2**

### Property 7: Unified Ledger Balance Calculation

*For any* member, the calculated balance should equal: opening_balance + deposits - withdrawals - shared_splits - personal_expenses + paid_expenses.

**Validates: Requirements 4.1**

### Property 8: Balance Recalculation on Changes

*For any* expense creation or deletion, all affected members' balances should be recalculated and updated in the database.

**Validates: Requirements 4.2, 12.2, 17.2**

### Property 9: Reference Uniqueness

*For any* two expenses in the system, their reference values should be unique (no duplicates allowed).

**Validates: Requirements 5.1, 5.2**

### Property 10: Atomic Transaction Rollback

*For any* expense creation that fails at any step, no partial data should remain in the database (complete rollback).

**Validates: Requirements 9.1, 9.2**

### Property 11: Minimum Amount Validation

*For any* expense creation request, the system should reject amounts less than or equal to 0.00.

**Validates: Requirements 10.1**

### Property 12: Cascade Delete Splits

*For any* expense deletion, all associated expense_splits records should be automatically deleted from the database.

**Validates: Requirements 12.1**

### Property 13: Member Relationship Integrity

*For any* expense, all referenced member_ids (payer_id, affected_member_id, splits.member_id) should exist in the members table.

**Validates: Requirements 17.1**

### Property 14: Split Distribution Completeness

*For any* shared expense, the number of expense_splits records should equal the number of members in the request.

**Validates: Requirements 2.7**

### Property 15: Expense Type Consistency

*For any* operational expense, both affected_member_id and splits should be null/empty, ensuring it's not assigned to specific members.

**Validates: Requirements 1.4**



## Error Handling

### Validation Errors (HTTP 422)

```php
// Example validation error response
{
    "message": "The given data was invalid.",
    "errors": {
        "expense_type": ["نوع المصروف غير صحيح"],
        "amount": ["المبلغ يجب أن يكون أكبر من 0.01"],
        "members": ["قائمة الأعضاء مطلوبة للمصروفات المشتركة"]
    }
}
```

### Business Logic Errors (HTTP 422)

```php
// Example: Manual split sum mismatch
{
    "message": "Manual split sum (95.50) does not match total amount (100.00)"
}
```

### Database Errors (HTTP 500)

```php
// Example: Transaction rollback
{
    "message": "Failed to create expense. Please try again."
}
```

### Error Handling Strategy

1. **Input Validation**: استخدام Laravel Form Requests للتحقق من البيانات
2. **Business Logic Validation**: التحقق في Controller قبل بدء Transaction
3. **Database Errors**: استخدام try-catch مع rollback تلقائي
4. **Logging**: تسجيل جميع الأخطاء في Laravel log
5. **User-Friendly Messages**: رسائل خطأ واضحة بالعربية

## Testing Strategy

### Dual Testing Approach

النظام يتطلب نوعين من الاختبارات:

1. **Unit Tests**: اختبار الوحدات الفردية (Models, Services)
2. **Property-Based Tests**: اختبار الخصائص الشاملة عبر مدخلات عشوائية

### Unit Testing

```php
// Example: Test Expense Model
class ExpenseTest extends TestCase
{
    public function test_generates_unique_reference()
    {
        $expense1 = Expense::factory()->create();
        $expense2 = Expense::factory()->create();
        
        $this->assertNotEquals($expense1->reference, $expense2->reference);
    }
    
    public function test_payer_relationship()
    {
        $member = Member::factory()->create();
        $expense = Expense::factory()->create(['payer_id' => $member->id]);
        
        $this->assertEquals($member->id, $expense->payer->id);
    }
}
```

### Property-Based Testing

سنستخدم مكتبة **Pest PHP** مع **Faker** لإنشاء بيانات عشوائية:

```php
// Example: Property Test for Equal Split Accuracy
test('equal split sum always equals total amount', function () {
    // Generate random expense data
    $totalAmount = fake()->randomFloat(2, 10, 1000);
    $memberCount = fake()->numberBetween(2, 10);
    $members = Member::factory()->count($memberCount)->create();
    
    // Create expense with equal split
    $expense = Expense::factory()->create([
        'expense_type' => 'shared',
        'amount' => $totalAmount
    ]);
    
    // Calculate splits using penny routing
    $splits = calculateEqualSplits($totalAmount, $members, $expense->reference);
    
    // Property: Sum of splits should equal total amount
    $splitSum = array_sum(array_column($splits, 'amount'));
    expect($splitSum)->toBe($totalAmount);
})->repeat(100); // Run 100 times with different random data
```

### Property Test Configuration

- **Minimum Iterations**: 100 مرة لكل اختبار خاصية
- **Test Tagging**: كل اختبار يحمل tag يشير إلى الخاصية المقابلة
- **Tag Format**: `Feature: expense-management-system, Property {number}: {property_text}`

### Test Coverage Goals

- **Unit Tests**: تغطية 80% من الكود على الأقل
- **Property Tests**: تغطية جميع الخصائص الـ 15
- **Integration Tests**: اختبار جميع API endpoints
- **Edge Cases**: اختبار الحالات الحدية (مبالغ صغيرة جداً، عدد كبير من الأعضاء)

### Testing Tools

- **PHPUnit**: إطار الاختبار الأساسي
- **Pest PHP**: بناء جملة أنظف للاختبارات
- **Laravel Factories**: توليد بيانات اختبار
- **Faker**: توليد بيانات عشوائية
- **Database Transactions**: عزل الاختبارات

## Performance Considerations

### Database Optimization

1. **Indexes**: فهارس على جميع الحقول المستخدمة في البحث والفلترة
2. **Eager Loading**: تحميل العلاقات مسبقاً لتجنب N+1 queries
3. **Caching**: تخزين مؤقت للإحصائيات المحسوبة
4. **Pagination**: تقسيم القوائم الطويلة إلى صفحات

### Query Optimization

```php
// Bad: N+1 Problem
$expenses = Expense::all();
foreach ($expenses as $expense) {
    echo $expense->payer->name; // Query for each expense
}

// Good: Eager Loading
$expenses = Expense::with(['payer', 'affectedMember', 'splits.member'])->get();
foreach ($expenses as $expense) {
    echo $expense->payer->name; // No additional queries
}
```

### Balance Calculation Optimization

```php
// Store calculated balance in database column
// Update only when needed (after expense operations)
// Avoid recalculating on every read

public function recalculateBalance(Member $member): void
{
    $newBalance = $member->calculated_balance; // Calculated once
    $member->update(['balance' => $newBalance]); // Stored for fast reads
}
```

## Security Considerations

### Input Validation

- استخدام Laravel Validation Rules
- التحقق من جميع المدخلات قبل المعالجة
- رفض القيم غير المتوقعة

### SQL Injection Prevention

- استخدام Eloquent ORM حصرياً
- عدم استخدام raw queries إلا عند الضرورة القصوى
- استخدام parameter binding في جميع الاستعلامات

### Authorization

- التحقق من صلاحيات المستخدم قبل كل عملية
- استخدام Laravel Policies للتحكم في الوصول
- تسجيل جميع العمليات الحساسة

### Audit Trail

```php
// Log all expense operations
Log::info('Expense created', [
    'expense_id' => $expense->id,
    'user_id' => auth()->id(),
    'amount' => $expense->amount,
    'type' => $expense->expense_type
]);
```

## Deployment Considerations

### Database Migrations

```bash
# Run migrations
php artisan migrate

# Rollback if needed
php artisan migrate:rollback
```

### Seeding Test Data

```bash
# Seed database with test data
php artisan db:seed --class=ExpenseSeeder
```

### Environment Configuration

```env
# .env file
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=finpartner
DB_USERNAME=root
DB_PASSWORD=
```

### Cache Clearing

```bash
# Clear all caches after deployment
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Future Enhancements

### Phase 2 Features

1. **Recurring Expenses**: مصروفات متكررة تلقائياً
2. **Expense Categories Management**: إدارة التصنيفات بشكل ديناميكي
3. **Expense Approval Workflow**: سير عمل للموافقة على المصروفات
4. **Advanced Reports**: تقارير متقدمة مع رسوم بيانية
5. **Export to Excel/PDF**: تصدير البيانات
6. **Expense Attachments**: إرفاق صور الفواتير
7. **Multi-Currency Support**: دعم عملات متعددة
8. **Budget Tracking**: تتبع الميزانيات والتنبيهات

### Scalability Considerations

- استخدام Queue Jobs للعمليات الثقيلة
- تطبيق Caching Strategy شاملة
- استخدام Database Replication للقراءة
- تطبيق API Rate Limiting
- استخدام CDN للملفات الثابتة

