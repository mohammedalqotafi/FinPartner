# Requirements Document - نظام إدارة المصروفات المتقدم

## Introduction

نظام إدارة المصروفات المتقدم هو نظام محاسبي شامل مصمم لإدارة جميع أنواع المصروفات في بيئة العمل الجماعي. يدعم النظام ثلاثة أنواع رئيسية من المصروفات: المصروفات المشتركة (Shared)، المصروفات التشغيلية (Operational)، والمصروفات الشخصية (Personal). يتميز النظام بدقة محاسبية عالية، تتبع كامل للمعاملات، وتكامل سلس مع نظام الأعضاء والمعاملات الموجود.

## Glossary

- **Expense_System**: نظام إدارة المصروفات الشامل
- **Member**: عضو في الفريق له حساب مالي في النظام
- **Expense**: مصروف مالي يتم تسجيله في النظام
- **Shared_Expense**: مصروف مشترك يتم تقسيمه بين عدة أعضاء
- **Operational_Expense**: مصروف تشغيلي عام للمؤسسة
- **Personal_Expense**: مصروف شخصي يخص عضو واحد فقط
- **Expense_Split**: حصة عضو من مصروف مشترك
- **Payer**: الشخص أو الجهة التي دفعت المصروف
- **Treasury**: خزانة النظام (عندما يكون الدافع هو النظام نفسه)
- **Split_Type**: نوع التقسيم (متساوي أو يدوي)
- **Penny_Routing**: خوارزمية توزيع الكسور العشرية بشكل حتمي
- **Unified_Ledger**: دفتر الأستاذ الموحد الذي يجمع جميع المعاملات والمصروفات
- **Balance_Calculation**: حساب الرصيد الشامل للعضو
- **Audit_Trail**: سجل التدقيق الكامل للعمليات

## Requirements

### Requirement 1: إدارة أنواع المصروفات الثلاثة

**User Story:** كمدير مالي، أريد تسجيل أنواع مختلفة من المصروفات (مشتركة، تشغيلية، شخصية)، حتى أتمكن من تصنيف وتتبع جميع النفقات بدقة.

#### Acceptance Criteria

1. WHEN يتم إنشاء مصروف جديد، THE Expense_System SHALL قبول أحد الأنواع الثلاثة: shared, operational, personal
2. WHEN يكون نوع المصروف shared، THE Expense_System SHALL طلب قائمة الأعضاء المشاركين ونوع التقسيم
3. WHEN يكون نوع المصروف personal، THE Expense_System SHALL طلب تحديد العضو المتأثر (affected_member_id)
4. WHEN يكون نوع المصروف operational، THE Expense_System SHALL تسجيله كمصروف عام بدون تخصيص لأعضاء محددين
5. THE Expense_System SHALL تخزين نوع المصروف في حقل expense_type بقاعدة البيانات

### Requirement 2: نظام التقسيم المتقدم للمصروفات المشتركة

**User Story:** كمدير مالي، أريد تقسيم المصروفات المشتركة بين الأعضاء بطريقة عادلة ودقيقة، حتى لا تضيع أي كسور عشرية وتكون الحسابات دقيقة 100%.

#### Acceptance Criteria

1. WHEN يتم اختيار التقسيم المتساوي (equal)، THE Expense_System SHALL حساب الحصة الأساسية لكل عضو بدقة عشرية
2. WHEN يوجد باقي كسور عشرية بعد التقسيم المتساوي، THE Expense_System SHALL استخدام خوارزمية Penny_Routing لتوزيع الباقي
3. THE Penny_Routing SHALL استخدام SHA-256 hash للـ expense_reference لتحديد العضو المستلم للكسور بشكل حتمي
4. WHEN يتم اختيار التقسيم اليدوي (manual)، THE Expense_System SHALL قبول مبالغ مخصصة لكل عضو
5. WHEN يتم التقسيم اليدوي، THE Expense_System SHALL التحقق من أن مجموع الحصص يساوي المبلغ الإجمالي بدقة
6. THE Expense_System SHALL رفض أي تقسيم يدوي لا يطابق المبلغ الإجمالي
7. FOR ALL shared expenses، THE Expense_System SHALL إنشاء سجلات في جدول expense_splits لكل عضو مشارك

### Requirement 3: إدارة الدافع (Payer Management)

**User Story:** كمدير مالي، أريد تسجيل من دفع المصروف (عضو أو الخزانة)، حتى أتمكن من تتبع المدفوعات وتعديل أرصدة الأعضاء بشكل صحيح.

#### Acceptance Criteria

1. WHEN يتم إنشاء مصروف، THE Expense_System SHALL قبول payer_id اختياري يشير إلى العضو الدافع
2. WHEN يكون payer_id فارغاً (null)، THE Expense_System SHALL اعتبار الدافع هو Treasury
3. WHEN يكون الدافع عضواً، THE Expense_System SHALL إضافة المبلغ المدفوع إلى رصيد العضو الإيجابي
4. THE Expense_System SHALL تخزين payer_id في جدول expenses
5. THE Expense_System SHALL عرض اسم الدافع في واجهة المستخدم (أو "خزانة" إذا كان null)

### Requirement 4: حساب الرصيد الموحد (Unified Ledger Balance)

**User Story:** كمدير مالي، أريد رؤية رصيد دقيق لكل عضو يشمل جميع المعاملات والمصروفات، حتى أحصل على صورة مالية كاملة ودقيقة.

#### Acceptance Criteria

1. THE Balance_Calculation SHALL حساب الرصيد النهائي للعضو بالمعادلة: opening_balance + deposits - withdrawals - shared_splits - personal_expenses + paid_expenses
2. WHEN يتم إنشاء أو حذف مصروف، THE Expense_System SHALL إعادة حساب أرصدة جميع الأعضاء المتأثرين
3. THE Expense_System SHALL تحديث حقل balance في جدول members بعد كل عملية
4. THE Expense_System SHALL استخدام accessor في Member model لحساب calculated_balance
5. THE Expense_System SHALL عرض الرصيد المحسوب في صفحة تفاصيل العضو

### Requirement 5: المرجع الفريد للمصروفات

**User Story:** كمدير مالي، أريد أن يكون لكل مصروف رقم مرجعي فريد، حتى أتمكن من تتبعه والرجوع إليه بسهولة.

#### Acceptance Criteria

1. THE Expense_System SHALL توليد reference فريد لكل مصروف بصيغة EXP-XXXX
2. THE Expense_System SHALL التحقق من عدم تكرار reference قبل الحفظ
3. THE Expense_System SHALL إنشاء index فريد على حقل reference في قاعدة البيانات
4. WHEN يتم إنشاء مصروف، THE Expense_System SHALL عرض reference في واجهة المستخدم
5. THE Expense_System SHALL السماح بالبحث عن المصروفات باستخدام reference

### Requirement 6: التصنيف والوصف التفصيلي

**User Story:** كمدير مالي، أريد تصنيف المصروفات ووصفها بشكل تفصيلي، حتى أتمكن من تحليل أنماط الإنفاق وإعداد التقارير.

#### Acceptance Criteria

1. THE Expense_System SHALL طلب category إلزامي لكل مصروف
2. THE Expense_System SHALL قبول description اختياري لتفاصيل إضافية
3. THE Expense_System SHALL تخزين category و description في جدول expenses
4. THE Expense_System SHALL السماح بالبحث والفلترة حسب category
5. THE Expense_System SHALL عرض category بشكل بارز في قوائم المصروفات

### Requirement 7: طرق الدفع المتعددة

**User Story:** كمدير مالي، أريد تسجيل طريقة الدفع المستخدمة، حتى أتمكن من تتبع التدفقات النقدية والبنكية.

#### Acceptance Criteria

1. THE Expense_System SHALL قبول payment_method إلزامي لكل مصروف
2. THE Expense_System SHALL دعم طرق الدفع التالية على الأقل: cash, transfer
3. THE Expense_System SHALL تخزين payment_method في جدول expenses
4. THE Expense_System SHALL السماح بالفلترة حسب payment_method
5. THE Expense_System SHALL عرض payment_method في تفاصيل المصروف

### Requirement 8: التاريخ والوقت الدقيق

**User Story:** كمدير مالي، أريد تسجيل التاريخ والوقت الفعلي للمصروف، حتى أتمكن من إعداد تقارير زمنية دقيقة.

#### Acceptance Criteria

1. THE Expense_System SHALL طلب expense_datetime إلزامي لكل مصروف
2. THE Expense_System SHALL قبول التاريخ والوقت بصيغة datetime كاملة
3. THE Expense_System SHALL تخزين expense_datetime منفصلاً عن created_at
4. THE Expense_System SHALL السماح بالفلترة حسب نطاق تاريخي
5. THE Expense_System SHALL ترتيب المصروفات حسب expense_datetime افتراضياً

### Requirement 9: المعاملات الذرية (Atomic Transactions)

**User Story:** كمطور نظام، أريد ضمان أن جميع عمليات المصروفات تتم بشكل ذري، حتى لا تحدث حالات عدم اتساق في البيانات.

#### Acceptance Criteria

1. WHEN يتم إنشاء مصروف مشترك، THE Expense_System SHALL استخدام database transaction لإنشاء الـ expense والـ splits معاً
2. IF فشلت أي خطوة في العملية، THEN THE Expense_System SHALL إلغاء جميع التغييرات (rollback)
3. THE Expense_System SHALL استخدام DB::beginTransaction و DB::commit و DB::rollBack
4. WHEN يتم حذف مصروف، THE Expense_System SHALL حذف جميع الـ splits المرتبطة به تلقائياً
5. THE Expense_System SHALL إعادة حساب الأرصدة بعد commit ناجح فقط

### Requirement 10: التحقق من صحة البيانات (Validation)

**User Story:** كمطور نظام، أريد التحقق من صحة جميع البيانات المدخلة، حتى أضمن سلامة البيانات ومنع الأخطاء.

#### Acceptance Criteria

1. THE Expense_System SHALL التحقق من أن amount أكبر من 0.01
2. THE Expense_System SHALL التحقق من أن expense_type من القيم المسموحة
3. WHEN يكون expense_type هو personal، THE Expense_System SHALL التحقق من وجود affected_member_id
4. WHEN يكون expense_type هو shared، THE Expense_System SHALL التحقق من وجود قائمة members غير فارغة
5. THE Expense_System SHALL التحقق من أن جميع member_ids موجودة في جدول members
6. THE Expense_System SHALL إرجاع رسائل خطأ واضحة عند فشل التحقق

### Requirement 11: واجهة برمجية RESTful

**User Story:** كمطور واجهة أمامية، أريد واجهة برمجية RESTful واضحة، حتى أتمكن من التكامل بسهولة مع النظام.

#### Acceptance Criteria

1. THE Expense_System SHALL توفير endpoint لإنشاء مصروف: POST /api/expenses
2. THE Expense_System SHALL توفير endpoint لعرض جميع المصروفات: GET /api/expenses
3. THE Expense_System SHALL توفير endpoint لعرض مصروف محدد: GET /api/expenses/{id}
4. THE Expense_System SHALL توفير endpoint لحذف مصروف: DELETE /api/expenses/{id}
5. THE Expense_System SHALL إرجاع responses بصيغة JSON
6. THE Expense_System SHALL إرجاع HTTP status codes مناسبة (200, 201, 422, 500)
7. THE Expense_System SHALL تضمين relationships (payer, affectedMember, splits) في الـ responses

### Requirement 12: الحذف الآمن والتدقيق

**User Story:** كمدير مالي، أريد القدرة على حذف المصروفات الخاطئة مع الحفاظ على سلامة البيانات، حتى أتمكن من تصحيح الأخطاء.

#### Acceptance Criteria

1. WHEN يتم حذف مصروف، THE Expense_System SHALL حذف جميع الـ splits المرتبطة به تلقائياً
2. WHEN يتم حذف مصروف، THE Expense_System SHALL إعادة حساب أرصدة جميع الأعضاء المتأثرين
3. THE Expense_System SHALL استخدام cascadeOnDelete في العلاقات
4. THE Expense_System SHALL تسجيل عملية الحذف في logs
5. THE Expense_System SHALL إرجاع رسالة نجاح بعد الحذف

### Requirement 13: واجهة مستخدم متقدمة

**User Story:** كمستخدم نهائي، أريد واجهة مستخدم سهلة وواضحة لإدارة المصروفات، حتى أتمكن من العمل بكفاءة.

#### Acceptance Criteria

1. THE Expense_System SHALL عرض قائمة المصروفات مع إمكانية البحث والفلترة
2. THE Expense_System SHALL توفير نموذج إضافة مصروف مع validation في الوقت الفعلي
3. WHEN يتم اختيار shared expense، THE Expense_System SHALL عرض واجهة اختيار الأعضاء والتقسيم
4. THE Expense_System SHALL عرض معاينة مباشرة لتوزيع المبالغ قبل الحفظ
5. THE Expense_System SHALL عرض مؤشر تحقق رياضي (Math Verified) عند تطابق المبالغ
6. THE Expense_System SHALL عرض إحصائيات ملخصة (إجمالي المصروفات، المشتركة، التشغيلية)
7. THE Expense_System SHALL دعم اللغة العربية بشكل كامل في الواجهة

### Requirement 14: التقارير والإحصائيات

**User Story:** كمدير مالي، أريد رؤية تقارير وإحصائيات عن المصروفات، حتى أتمكن من اتخاذ قرارات مالية مستنيرة.

#### Acceptance Criteria

1. THE Expense_System SHALL حساب إجمالي المصروفات المعروضة
2. THE Expense_System SHALL حساب إجمالي المصروفات المشتركة منفصلاً
3. THE Expense_System SHALL حساب إجمالي المصروفات التشغيلية منفصلاً
4. THE Expense_System SHALL عرض الإحصائيات في بطاقات ملونة واضحة
5. THE Expense_System SHALL تحديث الإحصائيات تلقائياً عند الفلترة

### Requirement 15: الأداء والتحسين

**User Story:** كمطور نظام، أريد أن يكون النظام سريعاً وفعالاً، حتى يتمكن المستخدمون من العمل بسلاسة.

#### Acceptance Criteria

1. THE Expense_System SHALL استخدام eager loading للعلاقات (with(['payer', 'splits.member']))
2. THE Expense_System SHALL إنشاء indexes على الحقول المستخدمة في البحث والفلترة
3. THE Expense_System SHALL تحميل المصروفات مرتبة حسب expense_datetime desc
4. THE Expense_System SHALL استخدام pagination عند عرض قوائم كبيرة
5. THE Expense_System SHALL تخزين الأرصدة المحسوبة في حقل balance لتجنب الحساب المتكرر

### Requirement 16: الأمان وصلاحيات الوصول

**User Story:** كمدير نظام، أريد التحكم في صلاحيات الوصول للمصروفات، حتى أحمي البيانات المالية الحساسة.

#### Acceptance Criteria

1. THE Expense_System SHALL التحقق من صلاحيات المستخدم قبل السماح بإنشاء مصروف
2. THE Expense_System SHALL التحقق من صلاحيات المستخدم قبل السماح بحذف مصروف
3. THE Expense_System SHALL تسجيل جميع العمليات في audit log
4. THE Expense_System SHALL حماية endpoints باستخدام middleware مناسب
5. THE Expense_System SHALL منع SQL injection و XSS attacks

### Requirement 17: التكامل مع نظام المعاملات

**User Story:** كمدير مالي، أريد أن يتكامل نظام المصروفات مع نظام المعاملات الموجود، حتى أحصل على رؤية مالية شاملة.

#### Acceptance Criteria

1. THE Expense_System SHALL استخدام نفس جدول members الموجود
2. THE Expense_System SHALL استدعاء TransactionService::recalculateBalance بعد كل عملية مصروف
3. THE Expense_System SHALL عرض المصروفات والمعاملات معاً في صفحة العضو
4. THE Expense_System SHALL استخدام نفس معايير الدقة العشرية (decimal 15,2)
5. THE Expense_System SHALL الحفاظ على اتساق البيانات بين الجدولين

### Requirement 18: معالجة الأخطاء والاستثناءات

**User Story:** كمطور نظام، أريد معالجة جميع الأخطاء المحتملة بشكل صحيح، حتى لا يتعطل النظام ويحصل المستخدم على رسائل واضحة.

#### Acceptance Criteria

1. THE Expense_System SHALL استخدام try-catch blocks في جميع العمليات الحرجة
2. WHEN يحدث خطأ، THE Expense_System SHALL إرجاع رسالة خطأ واضحة بالعربية
3. THE Expense_System SHALL تسجيل الأخطاء في log files
4. THE Expense_System SHALL إرجاع HTTP status code 422 للأخطاء في البيانات
5. THE Expense_System SHALL إرجاع HTTP status code 500 للأخطاء الداخلية
6. THE Expense_System SHALL عدم كشف تفاصيل تقنية حساسة في رسائل الخطأ للمستخدم

### Requirement 19: التوثيق والصيانة

**User Story:** كمطور نظام، أريد توثيقاً واضحاً للكود، حتى يسهل صيانته وتطويره مستقبلاً.

#### Acceptance Criteria

1. THE Expense_System SHALL تضمين تعليقات واضحة في الكود بالعربية والإنجليزية
2. THE Expense_System SHALL توثيق جميع الـ API endpoints
3. THE Expense_System SHALL توثيق جميع الـ database schemas
4. THE Expense_System SHALL توثيق خوارزمية Penny_Routing بالتفصيل
5. THE Expense_System SHALL توثيق معادلة حساب الرصيد الموحد

### Requirement 20: الاختبارات الآلية

**User Story:** كمطور نظام، أريد اختبارات آلية شاملة، حتى أضمن عمل النظام بشكل صحيح وأمنع الأخطاء المستقبلية.

#### Acceptance Criteria

1. THE Expense_System SHALL تضمين unit tests لجميع الـ models
2. THE Expense_System SHALL تضمين feature tests لجميع الـ API endpoints
3. THE Expense_System SHALL اختبار خوارزمية Penny_Routing بحالات متعددة
4. THE Expense_System SHALL اختبار حساب الرصيد الموحد بسيناريوهات مختلفة
5. THE Expense_System SHALL اختبار المعاملات الذرية والـ rollback
6. THE Expense_System SHALL تحقيق test coverage لا يقل عن 80%
