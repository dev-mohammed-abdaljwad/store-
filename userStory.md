# 🌿 نظام إدارة مخازن مبيدات ومخصبات زراعية
### Agricultural Pesticide & Fertilizer Store Management System
> **Stack:** Laravel · React · MySQL · Layered Architecture (Controller → Service → Repository → Model)

---

## 1. نظرة عامة على المشروع

نظام SaaS متعدد المستأجرين (Multi-Tenant) مخصص لأصحاب محلات بيع المبيدات الحشرية والأسمدة الزراعية.

**المبادئ الأساسية:**
- نظام نقدي بالكامل (Cash Only) — لا يوجد بوابة دفع
- عزل كامل بين البيانات (كل متجر يرى بياناته فقط)
- الأرصدة والمخزون تُحسب دائماً من الحركات — لا تُخزَّن مباشرةً
- دعم اللغة العربية كاملاً (RTL)
- لا يوجد نظام اشتراكات — الدفع يتم خارج النظام

---

## 2. أدوار المستخدمين

### 2.1 Super Admin (أنت)
- يُنشئ حسابات المتاجر ويديرها
- يفعّل / يوقف أي متجر
- يرى قائمة بجميع المتاجر وحالتها
- **لا يرى** أي بيانات مالية أو مخزون داخلية لأي متجر
- لوحة تحكم منفصلة تماماً عن لوحة أصحاب المتاجر

### 2.2 Store Owner (صاحب المتجر)
- حساب واحد لكل متجر (لا يوجد sub-users حالياً)
- Full access لكل بيانات متجره:
  - إدارة العملاء والموردين
  - إنشاء فواتير البيع والشراء
  - إدارة المنتجات والمخزون
  - عرض جميع التقارير
  - تصدير / backup لبياناته
- يرى تنبيهات عند انخفاض المخزون عن حد معين

---

## 3. قواعد المعمارية (Architecture Rules)

### 3.1 Multi-Tenant Isolation
```
❌ store_id لا يُقبل أبداً من الـ Request
✅ store_id يُستخرج دائماً من الـ Authenticated User
✅ كل Query مقيّدة تلقائياً بـ store_id عبر Global Scope
✅ Super Admin له Guard منفصل تماماً
```

### 3.2 No Direct Balance Storage
```
❌ لا يُخزَّن رصيد العميل أو المورد مباشرةً
✅ الرصيد = SUM(debit) - SUM(credit) من financial_transactions
✅ يُحسَّب في real-time أو عبر Cache محدودة الصلاحية
```

### 3.3 No Direct Stock Storage
```
❌ لا يُخزَّن الرصيد المخزني مباشرةً
✅ المخزون = SUM(quantity_in) - SUM(quantity_out) من stock_movements
✅ عند الاستعلام الكثيف: Materialized View أو Cached Snapshot
```

### 3.4 Layered Architecture
```
Request → Controller → Service → Repository → Model → Database
                ↑           ↑
           Validation    Business Logic
           (FormRequest)  (Service Layer)
```

---

## 4. قاعدة البيانات — Database Schema

### الجداول الأساسية

#### `stores`
| الحقل | النوع | الوصف |
|-------|-------|-------|
| id | BIGINT PK | |
| name | VARCHAR | اسم المتجر |
| owner_name | VARCHAR | اسم صاحب المتجر |
| email | VARCHAR UNIQUE | |
| phone | VARCHAR | |
| address | TEXT | |
| is_active | BOOLEAN | true = مفعّل |
| created_at / updated_at | TIMESTAMP | |

#### `users`
| الحقل | النوع | الوصف |
|-------|-------|-------|
| id | BIGINT PK | |
| store_id | BIGINT FK | NULL للـ Super Admin |
| name | VARCHAR | |
| email | VARCHAR UNIQUE | |
| password | VARCHAR | Hashed |
| role | ENUM(super_admin, store_owner) | |
| is_active | BOOLEAN | |
| created_at / updated_at | TIMESTAMP | |

#### `customers`
| الحقل | النوع | الوصف |
|-------|-------|-------|
| id | BIGINT PK | |
| store_id | BIGINT FK | |
| name | VARCHAR | |
| phone | VARCHAR | |
| address | TEXT | |
| notes | TEXT | |
| deleted_at | TIMESTAMP | Soft Delete |

#### `suppliers`
| الحقل | النوع | الوصف |
|-------|-------|-------|
| id | BIGINT PK | |
| store_id | BIGINT FK | |
| name | VARCHAR | |
| phone | VARCHAR | |
| address | TEXT | |
| notes | TEXT | |
| deleted_at | TIMESTAMP | Soft Delete |

#### `categories`
| الحقل | النوع | الوصف |
|-------|-------|-------|
| id | BIGINT PK | |
| store_id | BIGINT FK | |
| name | VARCHAR | مثال: مغذي، حشري، مبيد |
| deleted_at | TIMESTAMP | Soft Delete |

#### `products`
| الحقل | النوع | الوصف |
|-------|-------|-------|
| id | BIGINT PK | |
| store_id | BIGINT FK | |
| category_id | BIGINT FK | |
| name | VARCHAR | |
| sku | VARCHAR | كود المنتج |
| unit | VARCHAR | مثال: كيلو، لتر، كرتونة |
| purchase_price | DECIMAL(12,2) | |
| sale_price | DECIMAL(12,2) | |
| low_stock_threshold | INT | حد تنبيه انخفاض المخزون |
| deleted_at | TIMESTAMP | Soft Delete |

#### `stock_movements`
| الحقل | النوع | الوصف |
|-------|-------|-------|
| id | BIGINT PK | |
| store_id | BIGINT FK | |
| product_id | BIGINT FK | |
| reference_type | VARCHAR | invoice, purchase, adjustment, cancel |
| reference_id | BIGINT | ID الفاتورة أو المصدر |
| type | ENUM(in, out) | |
| quantity | DECIMAL(12,3) | |
| notes | TEXT | |
| created_by | BIGINT FK → users | |
| created_at | TIMESTAMP | |

#### `sales_invoices`
| الحقل | النوع | الوصف |
|-------|-------|-------|
| id | BIGINT PK | |
| store_id | BIGINT FK | |
| invoice_number | VARCHAR UNIQUE | INV-2024-0001 |
| customer_id | BIGINT FK | |
| total_amount | DECIMAL(12,2) | |
| paid_amount | DECIMAL(12,2) | |
| remaining_amount | DECIMAL(12,2) | total - paid |
| status | ENUM(draft, confirmed, cancelled) | |
| notes | TEXT | |
| cancelled_at | TIMESTAMP | |
| cancelled_by | BIGINT FK | |
| cancel_reason | TEXT | |
| created_by | BIGINT FK → users | |
| created_at / updated_at | TIMESTAMP | |

#### `sales_invoice_items`
| الحقل | النوع | الوصف |
|-------|-------|-------|
| id | BIGINT PK | |
| invoice_id | BIGINT FK | |
| product_id | BIGINT FK | |
| quantity | DECIMAL(12,3) | |
| unit_price | DECIMAL(12,2) | سعر وقت البيع |
| total_price | DECIMAL(12,2) | |

#### `purchase_invoices`
| الحقل | النوع | الوصف |
|-------|-------|-------|
| id | BIGINT PK | |
| store_id | BIGINT FK | |
| invoice_number | VARCHAR | |
| supplier_id | BIGINT FK | |
| total_amount | DECIMAL(12,2) | |
| paid_amount | DECIMAL(12,2) | |
| remaining_amount | DECIMAL(12,2) | |
| status | ENUM(draft, confirmed, cancelled) | |
| cancelled_at | TIMESTAMP | |
| cancelled_by | BIGINT FK | |
| cancel_reason | TEXT | |
| created_by | BIGINT FK → users | |
| created_at / updated_at | TIMESTAMP | |

#### `purchase_invoice_items`
| الحقل | النوع | الوصف |
|-------|-------|-------|
| id | BIGINT PK | |
| invoice_id | BIGINT FK | |
| product_id | BIGINT FK | |
| ordered_quantity | DECIMAL(12,3) | الكمية المطلوبة |
| received_quantity | DECIMAL(12,3) | الكمية المستلمة فعلياً |
| unit_price | DECIMAL(12,2) | |
| total_price | DECIMAL(12,2) | |

#### `financial_transactions`
| الحقل | النوع | الوصف |
|-------|-------|-------|
| id | BIGINT PK | |
| store_id | BIGINT FK | |
| party_type | ENUM(customer, supplier) | |
| party_id | BIGINT | |
| reference_type | VARCHAR | sales_invoice, purchase_invoice, payment, adjustment |
| reference_id | BIGINT | |
| type | ENUM(debit, credit) | |
| amount | DECIMAL(12,2) | |
| description | TEXT | |
| created_by | BIGINT FK → users | |
| created_at | TIMESTAMP | |

#### `cash_transactions`
| الحقل | النوع | الوصف |
|-------|-------|-------|
| id | BIGINT PK | |
| store_id | BIGINT FK | |
| type | ENUM(in, out, opening_balance) | |
| amount | DECIMAL(12,2) | |
| reference_type | VARCHAR | payment, expense, opening |
| reference_id | BIGINT | NULLABLE |
| description | TEXT | |
| transaction_date | DATE | |
| created_by | BIGINT FK → users | |
| created_at | TIMESTAMP | |

---

## 5. الـ User Stories الكاملة

### 5.1 Super Admin

**US-SA-1: إنشاء متجر جديد**
```
كـ Super Admin
أريد إنشاء حساب متجر جديد
حتى أتيح للعميل استخدام النظام

Acceptance Criteria:
✅ إنشاء سجل في stores
✅ إنشاء user بـ role = store_owner مربوط بالـ store_id
✅ كلمة المرور مشفرة
✅ المتجر يبدأ بحالة is_active = true
✅ إرسال بيانات الدخول للعميل (أو عرضها للـ Admin)
```

**US-SA-2: تفعيل / تعطيل متجر**
```
كـ Super Admin
أريد إيقاف أو تفعيل متجر

Acceptance Criteria:
✅ المتجر الموقوف لا يستطيع تسجيل الدخول
✅ الجلسات النشطة تنتهي فوراً عند الإيقاف
✅ البيانات لا تُحذف عند الإيقاف
```

**US-SA-3: عرض قائمة المتاجر**
```
Acceptance Criteria:
✅ اسم المتجر
✅ البريد الإلكتروني لصاحبه
✅ حالة التفعيل
✅ تاريخ الإنشاء
```

---

### 5.2 Authentication

**US-AUTH-1: تسجيل دخول صاحب المتجر**
```
Acceptance Criteria:
✅ التحقق من البريد وكلمة المرور
✅ رفض الدخول إذا كان المتجر غير مفعّل
✅ إنشاء جلسة آمنة (Sanctum Token)
✅ Guard منفصل عن Super Admin
```

**US-AUTH-2: تسجيل الخروج**
```
✅ حذف الـ Token الحالي فقط
```

**US-AUTH-3: انتهاء الجلسة تلقائياً**
```
✅ Token ينتهي بعد مدة خمول محددة
✅ الـ Frontend يُعاد توجيهه لصفحة الدخول
```

---

### 5.3 العملاء (Customers)

**US-C-1: إضافة عميل**
```
الحقول المطلوبة: الاسم (إلزامي)، الهاتف، العنوان، ملاحظات
✅ store_id تُؤخذ من الـ Auth User تلقائياً
```

**US-C-2: تعديل بيانات عميل**
```
✅ لا يمكن تعديل store_id
```

**US-C-3: عرض كشف حساب العميل**
```
يشمل:
✅ الفواتير (مع رقمها وتاريخها وإجماليها)
✅ المدفوعات
✅ التسويات
✅ الرصيد التراكمي بعد كل حركة
✅ فلترة بتاريخ (من / إلى)
```

**US-C-4: رصيد العميل اللحظي**
```
القاعدة:
رصيد مدين (عليه) = SUM(debit) - SUM(credit) من financial_transactions
رصيد دائن (له)  = SUM(credit) - SUM(debit) من financial_transactions
```

---

### 5.4 الموردون (Suppliers)

نفس قواعد العملاء تماماً مع عكس اتجاه المعاملات:

```
US-S-1: إضافة مورد
US-S-2: تعديل مورد
US-S-3: كشف حساب المورد
US-S-4: رصيد المورد اللحظي

اتجاه المعاملة:
- فاتورة شراء  → debit للمورد (المتجر مدين له)
- دفع للمورد   → credit للمورد (يُقلل الدين)
```

---

### 5.5 المنتجات والمخزون

**US-P-1: إضافة منتج**
```
الحقول:
✅ الاسم (إلزامي)
✅ التصنيف (مغذي / حشري / مبيد / أخرى)
✅ الوحدة (كيلو / لتر / كرتونة / علبة)
✅ سعر الشراء
✅ سعر البيع
✅ SKU / كود المنتج (للباركود مستقبلاً)
✅ حد التنبيه (low_stock_threshold)
```

**US-P-2: إدارة التصنيفات**
```
✅ إضافة / تعديل / حذف ناعم (Soft Delete)
✅ لا يمكن حذف تصنيف يحتوي على منتجات نشطة
   → رسالة خطأ واضحة: "يوجد X منتجات في هذا التصنيف"
```

**US-P-3: عرض المخزون الحالي**
```
المخزون = SUM(quantity حركات in) - SUM(quantity حركات out)
يُعرض لكل منتج:
✅ الكمية الحالية
✅ مؤشر تنبيه إذا كانت ≤ low_stock_threshold
```

**US-P-4: منع البيع عند نفاد المخزون**
```
✅ قبل حفظ فاتورة البيع: التحقق من توافر الكمية
✅ رسالة خطأ: "المخزون المتاح من [اسم المنتج] هو X فقط"
✅ لا يمكن تجاوز المخزون في أي حال
```

**US-P-5: تنبيه انخفاض المخزون**
```
✅ عند عرض قائمة المنتجات: تمييز المنتجات التي وصلت لحد التنبيه
✅ تنبيه في الـ Dashboard
```

---

### 5.6 فواتير البيع (Sales Invoices)

**US-SI-1: إنشاء فاتورة بيع**
```
الخطوات:
1. اختيار العميل
2. إضافة المنتجات (كمية + سعر)
3. النظام يحسب الإجمالي تلقائياً
4. إدخال المبلغ المدفوع
5. الحفظ

عند الحفظ، النظام يُنفذ (في Transaction واحدة):
✅ تخفيض المخزون → stock_movements (type: out)
✅ إنشاء قيد مالي في financial_transactions (debit للعميل)
✅ إنشاء قيد نقدي في cash_transactions (in) إذا paid_amount > 0
✅ توليد invoice_number تلقائياً (INV-YYYY-NNNN)
```

**US-SI-2: الدفع الجزئي**
```
✅ تُسجَّل قيمة الفاتورة كاملة كـ debit
✅ المبلغ المدفوع يُسجَّل كـ credit
✅ الفرق = رصيد مدين على العميل
✅ يمكن تسجيل دفعة لاحقة على نفس الفاتورة
```

**US-SI-3: إلغاء فاتورة بيع**
```
✅ لا حذف — تغيير status إلى cancelled فقط
✅ عكس حركة المخزون (stock_movements type: in)
✅ عكس القيد المالي (credit للعميل)
✅ عكس القيد النقدي إذا كان هناك مدفوع

حالة خاصة — إلغاء فاتورة مدفوعة جزئياً:
✅ المبلغ المدفوع يتحول إلى رصيد دائن (للعميل)
   → يُستخدم في فواتير مستقبلية أو يُرجَّع نقداً
✅ يجب تسجيل سبب الإلغاء (cancel_reason)
```

---

### 5.7 فواتير الشراء (Purchase Invoices)

**US-PI-1: إنشاء فاتورة شراء**
```
عند الحفظ:
✅ زيادة المخزون بـ received_quantity (وليس ordered_quantity)
✅ إنشاء قيد مالي (debit = مديونية للمورد)
✅ paid_amount > 0 → cash_transactions (out)
```

**US-PI-2: الدفع الجزئي للمورد**
```
✅ نفس منطق فاتورة البيع — الفرق يبقى ديناً للمورد
✅ يمكن تسجيل دفعة لاحقة للمورد
```

**US-PI-3: إلغاء فاتورة شراء**
```
✅ عكس حركة المخزون
✅ عكس القيد المالي للمورد
✅ عكس النقدية إذا وُجدت
✅ تسجيل سبب الإلغاء إلزامي
```

---

### 5.8 المعاملات النقدية (Cash)

**US-CA-1: تسجيل رصيد افتتاحي**
```
✅ عند إنشاء المتجر لأول مرة
✅ إدخال الكاش الموجود في الدرج
✅ يُسجَّل كـ cash_transaction (type: opening_balance)
```

**US-CA-2: تحصيل نقدي من عميل (بدون فاتورة)**
```
✅ اختيار العميل
✅ إدخال المبلغ
✅ يُنشئ: financial_transaction (credit) + cash_transaction (in)
```

**US-CA-3: دفع نقدي لمورد (بدون فاتورة)**
```
✅ اختيار المورد
✅ إدخال المبلغ
✅ يُنشئ: financial_transaction (credit) + cash_transaction (out)
```

**US-CA-4: تقرير النقدية اليومي**
```
يعرض:
✅ إجمالي الوارد (Cash In)
✅ إجمالي الصادر (Cash Out)
✅ صافي الكاش = رصيد افتتاحي + in - out
✅ فلترة بتاريخ
```

---

### 5.9 Backup / تصدير البيانات

**US-BK-1: تصدير بيانات المتجر**
```
كـ Store Owner
أريد تصدير بياناتي
حتى أحتفظ بنسخة احتياطية

Acceptance Criteria:
✅ تصدير Excel / CSV لـ:
   - العملاء
   - الموردين
   - المنتجات
   - الفواتير
   - كشوف الحساب
✅ البيانات خاصة بمتجره فقط
```

---

### 5.10 التقارير (Reports)

| الكود | التقرير | الفلاتر |
|-------|---------|---------|
| US-R-1 | تقرير المبيعات اليومي | تاريخ |
| US-R-2 | تقرير المبيعات الشهري | شهر / سنة |
| US-R-3 | تقرير المشتريات | تاريخ / مورد |
| US-R-4 | تقرير المخزون الحالي | تصنيف / منتج |
| US-R-5 | تقرير ديون العملاء | عميل / تاريخ |
| US-R-6 | تقرير المستحقات للموردين | مورد / تاريخ |
| US-R-7 | تقرير حركة منتج | منتج / تاريخ |
| US-R-8 | تقرير النقدية | تاريخ (من/إلى) |

---

## 6. قواعد العمل الحرجة (Critical Business Rules)

### 6.1 منطق الرصيد المالي
```
رصيد العميل:
├── موجب (+) = العميل مدين = عليه مبالغ
└── سالب (-) = للعميل رصيد دائن = له مبالغ

رصيد المورد:
├── موجب (+) = المتجر مدين للمورد = عليه مبالغ
└── سالب (-) = المورد مدين للمتجر (دفعنا أكثر)
```

### 6.2 إلغاء فاتورة مدفوعة جزئياً
```
حالة: فاتورة بـ 1000 جنيه، دُفع منها 400

عند الإلغاء:
→ الـ 1000 تُعكس (لا دين على العميل)
→ الـ 400 تتحول لـ رصيد دائن للعميل
→ تُسجَّل في financial_transactions كـ credit بقيمة 400
→ يظهر في كشف حساب العميل: "رصيد دائن 400 جنيه"
```

### 6.3 دفعة أكبر من الدين
```
حالة: رصيد العميل 300 جنيه، دفع 500

الحكم: مقبول ✅
→ الـ 500 تُسجَّل كاملة
→ الرصيد الجديد = -200 (رصيد دائن للعميل)
→ يُنبَّه المستخدم: "تم تسجيل رصيد دائن 200 جنيه للعميل"
```

### 6.4 حماية التزامن (Concurrency)
```
عند إنشاء فاتورة بيع:
→ استخدام DB Transaction
→ Pessimistic Lock على حركات المخزون
→ منع بيع نفس الكمية مرتين في نفس اللحظة
```

### 6.5 Idempotency للحفظ المزدوج
```
→ كل فاتورة لها invoice_number فريد
→ محاولة حفظ مكررة ترفض بـ unique constraint
→ الـ Frontend يُعطَّل زر الحفظ بعد أول ضغطة
```

---

## 7. الـ Edge Cases وحلولها

| الحالة | الحل |
|--------|------|
| بيع كمية أكبر من المخزون | رفض مع رسالة خطأ واضحة |
| إلغاء فاتورة بعد الدفع | عكس الحركات + رصيد دائن للعميل/مورد |
| دفعة أكبر من الدين | قبول + تسجيل رصيد دائن + تنبيه |
| حفظ مزدوج للفاتورة | unique invoice_number + تعطيل الزر |
| انتهاء الجلسة أثناء الفاتورة | حفظ draft + إعادة التوجيه للدخول |
| إيقاف المتجر أثناء الجلسة | إنهاء الجلسة فوراً + رسالة توضيحية |
| طلبان متزامنان على نفس المخزون | DB Transaction + Pessimistic Lock |
| حذف تصنيف له منتجات | رفض مع عداد المنتجات المرتبطة |

---

## 8. متطلبات غير وظيفية

| المتطلب | التفاصيل |
|---------|---------|
| **الأمان** | Sanctum Tokens · Hashed Passwords · store_id من Auth دائماً |
| **سلامة البيانات** | Soft Delete لكل السجلات · DB Transactions للعمليات المركبة |
| **Audit Trail** | created_by / cancelled_by / updated_by + timestamps على كل جدول |
| **الأداء** | Cache لأرصدة المخزون والمالية عند الحاجة |
| **اللغة** | Arabic RTL · ملفات ترجمة لكل النصوص |
| **الـ API** | RESTful JSON API جاهز للـ Mobile مستقبلاً |
| **Backup** | تصدير Excel/CSV لكل بيانات المتجر |

---

## 9. خارطة التطوير

### 🟢 Phase 1 — Core Backend (الأولوية القصوى)
- [ ] Database Migrations كاملة
- [ ] Multi-Tenant Global Scope
- [ ] Auth (Super Admin + Store Owner Guards)
- [ ] CRUD: Customers, Suppliers, Products, Categories
- [ ] Sales Invoice Engine (مع Stock + Financial + Cash)
- [ ] Purchase Invoice Engine

### 🟡 Phase 2 — Financial & Reports
- [ ] Cash Transactions (opening balance + payments)
- [ ] Financial Ledger (balance calculations)
- [ ] Reports (المبيعات، المخزون، الديون، النقدية)
- [ ] Low Stock Alerts
- [ ] Backup / Export

### 🔵 Phase 3 — Frontend (React)
- [ ] Super Admin Dashboard
- [ ] Store Owner Dashboard
- [ ] Invoice Builder UI
- [ ] Reports UI

---

> **ملاحظة للمطور:** كل عملية تمس المخزون أو المالية أو النقدية **يجب** أن تُنفَّذ داخل `DB::transaction()` واحدة. أي فشل في أي خطوة يُلغي كل شيء.