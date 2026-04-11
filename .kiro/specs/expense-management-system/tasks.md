# Implementation Plan: نظام إدارة المصروفات المتقدم

## Overview

خطة تنفيذ شاملة لبناء نظام إدارة المصروفات المتقدم بالتكامل الكامل مع النظام المالي الحالي. التنفيذ يتبع منهجية تدريجية مع اختبارات شاملة لضمان الدقة المحاسبية والأداء العالي.

## Tasks

- [x] 1. تحسين جداول قاعدة البيانات والعلاقات
  - مراجعة وتحسين migrations الموجودة (expenses و expense_splits)
  - إضافة indexes محسّنة للأداء
  - التأكد من foreign keys و constraints الصحيحة
  - _Requirements: 1.1, 5.1, 15.1_

- [x] 1.1 كتابة اختبارات للـ migrations
  - **Property 9: Reference Uniqueness**
  - **Validates: Requirements 5.1, 5.2**

- [x] 2. تحسين Expense Model
  - [x] 2.1 إضافة casts للحقول (amount, expense_datetime)
    - تحديد decimal:2 للـ amount
    - تحديد datetime للـ expense_datetime
    - _Requirements: 2.1, 8.1_

  - [x] 2.2 إضافة العلاقات (payer, affectedMember, splits)
    - تعريف belongsTo للـ payer
    - تعريف belongsTo للـ affectedMember
    - تعريف hasMany للـ splits
    - _Requirements: 3.1, 11.7_

  - [x] 2.3 إضافة method لتوليد reference فريد
    - إنشاء generateReference() static method
    - استخدام صيغة EXP-XXXX
    - التحقق من عدم التكرار
    - _Requirements: 5.1, 5.2_

  - [x] 2.4 كتابة property test لتوليد المراجع
    - **Property 9: Reference Uniqueness**
    - **Validates: Requirements 5.1, 5.2**

- [x] 3. تحسين ExpenseSplit Model
  - [x] 3.1 إضافة casts للحقول
    - تحديد decimal:2 للـ amount
    - _Requirements: 2.1_

  - [x] 3.2 إضافة العلاقات (expense, member)
    - تعريف belongsTo للـ expense
    - تعريف belongsTo للـ member
    - _Requirements: 17.1_

  - [x] 3.3 كتابة unit tests للـ ExpenseSplit model
    - اختبار العلاقات
    - اختبار الـ casts
    - _Requirements: 2.1, 17.1_

- [x] 4. تحسين Member Model
  - [x] 4.1 إضافة علاقات المصروفات الجديدة
    - إضافة expenseSplits() hasMany relationship
    - إضافة personalExpenses() hasMany relationship
    - إضافة paidExpenses() hasMany relationship
    - _Requirements: 17.1_

  - [x] 4.2 تحسين accessor لحساب الرصيد الموحد
    - تحديث getCalculatedBalanceAttribute()
    - إضافة حساب shared_splits
    - إضافة حساب personal_expenses
    - إضافة حساب paid_expenses
    - _Requirements: 4.1_

  - [x] 4.3 كتابة property test لحساب الرصيد الموحد
    - **Property 7: Unified Ledger Balance Calculation**
    - **Validates: Requirements 4.1**

- [x] 5. Checkpoint - التحقق من Models والعلاقات
  - التأكد من عمل جميع العلاقات بشكل صحيح
  - اختبار حساب الرصيد الموحد
  - مراجعة الكود مع المستخدم

- [x] 6. إنشاء Form Request للتحقق من البيانات
  - [x] 6.1 إنشاء StoreExpenseRequest
    - إضافة validation rules لجميع الحقول
    - إضافة custom validation للـ expense_type
    - إضافة custom validation للـ shared expenses
    - إضافة custom validation للـ personal expenses
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

  - [x] 6.2 كتابة property tests للـ validation
    - **Property 1: Expense Type Validation**
    - **Property 2: Required Fields Based on Type**
    - **Property 11: Minimum Amount Validation**
    - **Validates: Requirements 1.1, 1.2, 1.3, 10.1, 10.2**

- [x] 7. تطوير ExpenseController - الجزء الأول (Read Operations)
  - [x] 7.1 تطوير index() method
    - إضافة eager loading للعلاقات
    - إضافة ordering حسب expense_datetime
    - إرجاع JSON response
    - _Requirements: 11.2, 15.2_

  - [x] 7.2 تطوير show() method
    - إضافة eager loading للعلاقات
    - إرجاع JSON response مع التفاصيل الكاملة
    - _Requirements: 11.3, 11.7_

  - [x] 7.3 كتابة feature tests للـ read operations
    - اختبار GET /api/expenses
    - اختبار GET /api/expenses/{id}
    - _Requirements: 11.2, 11.3_

- [x] 8. تطوير خوارزمية Penny Routing
  - [x] 8.1 إنشاء helper function لحساب التقسيم المتساوي
    - حساب الحصة الأساسية
    - حساب الباقي (remainder)
    - استخدام SHA-256 hash للـ reference
    - توزيع الباقي بشكل حتمي
    - _Requirements: 2.1, 2.2, 2.3_

  - [x] 8.2 كتابة property tests لخوارزمية Penny Routing
    - **Property 3: Equal Split Mathematical Accuracy**
    - **Property 4: Penny Routing Determinism**
    - **Validates: Requirements 2.1, 2.2, 2.3**

- [x] 9. تطوير ExpenseController - الجزء الثاني (Create Operation)
  - [x] 9.1 تطوير store() method - البنية الأساسية
    - استخدام StoreExpenseRequest للتحقق
    - بدء database transaction
    - إنشاء Expense record
    - _Requirements: 11.1, 9.1_

  - [x] 9.2 تطوير store() method - معالجة Shared Expenses
    - التحقق من split_type
    - حساب التقسيمات (equal أو manual)
    - إنشاء ExpenseSplit records
    - _Requirements: 2.1, 2.2, 2.4, 2.5, 2.7_

  - [x] 9.3 تطوير store() method - معالجة Personal Expenses
    - التحقق من affected_member_id
    - حفظ المصروف بدون splits
    - _Requirements: 1.3_

  - [x] 9.4 تطوير store() method - معالجة Operational Expenses
    - حفظ المصروف بدون affected_member أو splits
    - _Requirements: 1.4_

  - [x] 9.5 تطوير store() method - إعادة حساب الأرصدة
    - جمع جميع الأعضاء المتأثرين
    - استدعاء TransactionService::recalculateBalance
    - _Requirements: 4.2, 17.2_

  - [x] 9.6 تطوير store() method - معالجة الأخطاء
    - إضافة try-catch block
    - rollback عند الفشل
    - إرجاع رسائل خطأ واضحة
    - _Requirements: 9.2, 18.1, 18.2_

  - [x] 9.7 كتابة property tests لـ store operation
    - **Property 3: Equal Split Mathematical Accuracy**
    - **Property 5: Manual Split Sum Verification**
    - **Property 6: Payer Balance Contribution**
    - **Property 8: Balance Recalculation on Changes**
    - **Property 10: Atomic Transaction Rollback**
    - **Property 14: Split Distribution Completeness**
    - **Validates: Requirements 2.1, 2.2, 2.4, 2.5, 3.2, 4.2, 9.1, 9.2**

- [x] 10. Checkpoint - اختبار Create Operation
  - اختبار إنشاء مصروف مشترك بتقسيم متساوي
  - اختبار إنشاء مصروف مشترك بتقسيم يدوي
  - اختبار إنشاء مصروف شخصي
  - اختبار إنشاء مصروف تشغيلي
  - التحقق من دقة حساب الأرصدة
  - مراجعة الكود مع المستخدم

- [x] 11. تطوير ExpenseController - الجزء الثالث (Delete Operation)
  - [x] 11.1 تطوير destroy() method
    - جمع الأعضاء المتأثرين قبل الحذف
    - حذف المصروف (cascade delete للـ splits)
    - إعادة حساب أرصدة الأعضاء المتأثرين
    - _Requirements: 12.1, 12.2, 12.4_

  - [x] 11.2 كتابة property tests لـ delete operation
    - **Property 12: Cascade Delete Splits**
    - **Property 8: Balance Recalculation on Changes**
    - **Validates: Requirements 12.1, 12.2**

- [x] 12. تحديث API Routes
  - إضافة routes للـ ExpenseController
  - التأكد من استخدام apiResource
  - إضافة middleware للحماية
  - _Requirements: 11.1, 11.2, 11.3, 11.5, 16.4_

- [x] 13. إنشاء Expense Factory للاختبارات
  - إنشاء ExpenseFactory
  - إنشاء ExpenseSplitFactory
  - إضافة states مختلفة (shared, personal, operational)
  - _Requirements: 20.1, 20.2_

- [x] 14. Checkpoint - اختبار Backend بالكامل
  - تشغيل جميع الاختبارات
  - التحقق من test coverage
  - مراجعة الأداء
  - مراجعة الكود مع المستخدم

- [x] 15. تطوير Frontend - Types و Interfaces
  - [x] 15.1 تحديث expenses.types.ts
    - التأكد من تطابق Types مع Backend
    - إضافة أي types ناقصة
    - _Requirements: 11.7_

  - [x] 15.2 تحديث api.ts
    - التأكد من صحة base URL
    - إضافة error handling
    - _Requirements: 11.5_

- [x] 16. تطوير Frontend - Expenses Service
  - [x] 16.1 تحديث expenses.service.ts
    - التأكد من صحة جميع API calls
    - إضافة error handling
    - إضافة type safety
    - _Requirements: 11.1, 11.2, 11.3, 11.5_

  - [x] 16.2 كتابة unit tests للـ service
    - اختبار جميع API calls
    - اختبار error handling
    - _Requirements: 11.1, 11.2, 11.3_

- [-] 17. تطوير Frontend - ExpenseForm Component
  - [x] 17.1 تحسين validation في الوقت الفعلي
    - إضافة validation rules باستخدام zod
    - عرض رسائل خطأ واضحة
    - _Requirements: 13.2_

  - [x] 17.2 تحسين واجهة التقسيم المشترك
    - تحسين UI لاختيار الأعضاء
    - تحسين UI للتقسيم المتساوي/اليدوي
    - _Requirements: 13.3_

  - [x] 17.3 تحسين المعاينة المباشرة
    - عرض توزيع المبالغ في الوقت الفعلي
    - استخدام نفس خوارزمية Penny Routing
    - عرض مؤشر التحقق الرياضي
    - _Requirements: 13.4, 13.5_

  - [x] 17.4 تحسين معالجة الأخطاء
    - عرض رسائل خطأ من Backend
    - معالجة حالات الفشل
    - _Requirements: 18.2_

  - [x] 17.5 كتابة component tests للـ ExpenseForm
    - اختبار validation
    - اختبار حساب التقسيمات
    - اختبار submission
    - _Requirements: 13.2, 13.3, 13.4_

- [-] 18. تطوير Frontend - ExpensesPage Component
  - [x] 18.1 تحسين عرض القائمة
    - تحسين table layout
    - إضافة sorting
    - تحسين responsive design
    - _Requirements: 13.1_

  - [x] 18.2 تحسين البحث والفلترة
    - تحسين search functionality
    - تحسين filter options
    - إضافة date range filter
    - _Requirements: 5.5, 6.4, 8.4_

  - [x] 18.3 تحسين الإحصائيات
    - حساب الإحصائيات بشكل صحيح
    - تحديث تلقائي عند الفلترة
    - عرض بطاقات ملونة
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5_

  - [ ] 18.4 كتابة component tests للـ ExpensesPage
    - اختبار loading state
    - اختبار filtering
    - اختبار statistics
    - _Requirements: 13.1, 14.1_

- [x] 19. تطوير Frontend - ExpenseDetails Component
  - [ ] 19.1 إنشاء ExpenseDetails component
    - عرض جميع تفاصيل المصروف
    - عرض التقسيمات إن وجدت
    - عرض معلومات الدافع
    - _Requirements: 11.7_

  - [x] 19.2 كتابة component tests للـ ExpenseDetails
    - اختبار عرض التفاصيل
    - اختبار عرض التقسيمات
    - _Requirements: 11.7_

- [x] 20. تحسين تكامل Member Page
  - [x] 20.1 إضافة عرض المصروفات في صفحة العضو
    - عرض المصروفات الشخصية
    - عرض التقسيمات المشتركة
    - عرض المصروفات المدفوعة
    - _Requirements: 17.3_

  - [x] 20.2 تحسين عرض الرصيد الموحد
    - عرض الرصيد المحسوب الشامل
    - عرض تفصيل الرصيد (deposits, withdrawals, expenses)
    - _Requirements: 4.5_

- [x] 21. Checkpoint - اختبار Frontend بالكامل
  - اختبار جميع الصفحات والمكونات
  - اختبار التكامل مع Backend
  - اختبار responsive design
  - مراجعة UX مع المستخدم

- [-] 22. اختبارات التكامل الشاملة
  - [x] 22.1 كتابة end-to-end tests
    - اختبار سيناريو كامل: إنشاء مصروف → عرض → حذف
    - اختبار تأثير المصروفات على الأرصدة
    - اختبار جميع أنواع المصروفات
    - _Requirements: 20.1, 20.2, 20.3, 20.4_

  - [-] 22.2 كتابة property tests للسيناريوهات المعقدة
    - **Property 7: Unified Ledger Balance Calculation**
    - **Property 8: Balance Recalculation on Changes**
    - **Property 13: Member Relationship Integrity**
    - **Validates: Requirements 4.1, 4.2, 17.1**

- [x] 23. تحسينات الأداء
  - [x] 23.1 إضافة indexes إضافية إن لزم الأمر
    - تحليل slow queries
    - إضافة composite indexes
    - _Requirements: 15.2_

  - [x] 23.2 تحسين eager loading
    - مراجعة جميع queries
    - إضافة eager loading حيث يلزم
    - _Requirements: 15.1_

  - [x] 23.3 إضافة pagination
    - إضافة pagination للقوائم الطويلة
    - تحسين performance
    - _Requirements: 15.4_

- [ ] 24. التوثيق والتعليقات
  - [ ] 24.1 توثيق API endpoints
    - كتابة API documentation
    - إضافة examples
    - _Requirements: 19.2_

  - [ ] 24.2 توثيق الكود
    - إضافة تعليقات واضحة
    - توثيق خوارزمية Penny Routing
    - توثيق معادلة الرصيد الموحد
    - _Requirements: 19.1, 19.4, 19.5_

  - [ ] 24.3 إنشاء README للمصروفات
    - شرح النظام
    - شرح كيفية الاستخدام
    - شرح كيفية الاختبار
    - _Requirements: 19.1_

- [ ] 25. Checkpoint النهائي
  - تشغيل جميع الاختبارات (Unit + Property + Integration)
  - التحقق من test coverage (هدف 80%+)
  - مراجعة الأداء والأمان
  - مراجعة شاملة مع المستخدم
  - الموافقة النهائية للنشر

## Notes

- جميع المهام إلزامية لضمان تغطية شاملة وجودة عالية
- كل مهمة تشير إلى المتطلبات المرتبطة بها للتتبع
- Checkpoints مهمة للتأكد من سير العمل بشكل صحيح قبل المتابعة
- Property tests تستخدم 100 iteration على الأقل لكل اختبار
- جميع الاختبارات يجب أن تمر قبل الانتقال للمرحلة التالية
