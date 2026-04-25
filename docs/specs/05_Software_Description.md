# InstaParty — وصف التطبيق / Software Description

> **Source:** `InstaParty_App.pdf` (original client brief, Arabic with mixed English technical terms)
> **This document:** Cleaned-up bilingual rendering. Arabic text on top, English translation/notes below each section.

---

## 1. نظرة عامة على المنتج / Product Overview

### العربية

تطبيق خاص بتنظيم الحفلات وأعياد الميلاد.

**Marketplace Multivendor** لأصناف مختلفة، مثل:

- **أصناف للإيجار** في أيام و أوقات محددة:
  - الألعاب الهوائية
  - الشوهات (المسوخ/الشخصيات الكرتونية)
  - الديكورات
- **أصناف عادية للبيع**:
  - التورتة
  - المأكولات
  - هدايا الأطفال
- **أصناف ديجيتال**:
  - دعاوى ديجيتال (الدعوات الإلكترونية)
  - تطبيق لصور الحفلة
  - لينك لشراء هدية لعيد الميلاد

المطلوب موقع إلكتروني وتطبيق محمول يعمل من ثلاث جهات:

- العميل
- المورد
- إدارة المنصة

### English

InstaParty is an app for organizing parties and birthday events. It's a **multi-vendor marketplace** with three structurally distinct product types:

- **Rental items** (booked for specific days and time slots):
  - Inflatables / bouncy castles
  - Mascots & character costumes
  - Decorations
- **Sale items** (regular goods):
  - Cakes
  - Food
  - Children's gifts
- **Digital items**:
  - E-invitations
  - Photo / event apps
  - Gift purchase links

**Deliverables:** website + mobile app supporting three roles — Customer, Vendor, Platform Admin.

> **Note (locked Tech Decisions §2):** these three types are first-class domain concepts. They drive schema (polymorphic base + 3 detail tables), validation (per-type Form Requests), fulfillment (3 state machines), pricing (3 calculators), refunds (per-type policies), notifications (per-type templates), and Excel imports (3 templates).

---

## 2. تعامل العميل مع المنصة / Customer Flow

### العربية

عند دخول العميل للمنصة:

1. **اختيار نوع الحفلة المطلوب تنظيمها**:
   - عيد ميلاد
   - فرح / خطوبة

2. **اختيار مجموعة الاتجاهات الرئيسية**:

   **مثال — عيد ميلاد:**
   - الألعاب الترفيهية
   - الأنشطة المختلفة
   - الشوهات المختلفة
   - الديكورات
   - المأكولات والسناكس
   - الخدمات الإضافية
   - أماكن مناسبة لتنظيم الحفلات

   **مثال — فرح / خطوبة:**
   - الكوشة
   - الديكورات
   - البوفيه
   - المغني
   - الزفة
   - الخدمات الإضافية
   - أماكن مناسبة لتنظيم الفرح

3. ثم يظهر أمامه جميع الموردين المحتملين لتوريد الأصناف من هذه المجموعة.

4. ثم يختار أحد الموردين ويبحث عن الأصناف المطلوبة من بين منتجاته.

5. ثم يمكن للعميل الرجوع للمجموعات الرئيسية مرة أخرى لتحديد مجموعة أخرى ثم تكملة الطلبية من مورد آخر.

6. ثم يقوم بإنهاء الطلبية ويتم تقسيم الطلبية على الموردين وإرسال الأصناف الخاصة بكل مورد له في طلبية مستقلة.

### English

Customer journey:

1. **Select event type:** birthday, wedding, engagement
2. **Select main category groups** appropriate to the event type
3. **Browse vendors** offering items in those categories
4. **Pick services** from a vendor's catalog
5. **Loop back** to add items from other categories / other vendors
6. **Submit booking** — system splits the order per vendor and routes each part to the relevant vendor for review

> Maps directly to PRD §6.1 and FR-1 through FR-9.

---

## 3. بيانات هامة عند الطلب / Critical Booking Data

### العربية

- عند بداية عمل الطلبية مطلوب **تحديد اليوم وميعاد بداية الحفلة**.
- عند اختيار أي بند خدمي يكون الميعاد مطابق لميعاد الحفلة.
- عند اختيار بعض الأصناف مثل الشوهات أو الأنشطة التي يكون لها فترة محددة، **لا بد من تحديد ميعاد البداية المطلوب**.
- لا بد من **تحديد مكان إقامة الحفلة** في حالة أنه يختلف عن مكان الإقامة، ومطلوب لوكيشن (Location).
- لا بد من إمكانية عمل **ريفيوهات (Reviews)** على مستوى الصنف وأيضاً على مستوى المورد.
- لا بد أن تكون الريفيوهات ظاهرة للعميل عند تصفحه للموقع لعمل الطلبية.
- يمكن للعميل **كتابة بيانات إضافية** في نهاية الطلب وشرح لطلباته.
- لا بد من المورد عمل **Accept** ليظهر للعميل أن الطلبية تمت وعليه سداد المقدم.
- يمكن **التعديل على الطلبية** من جهة العميل أو من جهة المورد في أي وقت حتى تاريخ محدد.
- هل مطلوب **فاتورة ضريبية**؟ ما الاسم المطلوب؟
- يتم إضافة **بند النقل** للطلبية بناءً على سياسة خاصة بالمورد، ويمكن للمورد إضافة بند نقل إضافي على الطلبية.

### English

| Decision | Behavior |
|---|---|
| Event date + start time | **Required** at booking start |
| Per-item slot | Defaults to event slot |
| Short-duration items (mascots, activities) | Customer **must** set their own start time within the event range |
| Event location | Required separately if different from customer's home; geolocation needed |
| Reviews | At both **service** level and **vendor** level |
| Reviews visibility | Visible to customers while browsing |
| Customer notes | Free-text field at end of booking |
| Vendor `Accept` | Required before customer pays the deposit |
| Modifications | Either side can modify until a cutoff date |
| Tax invoice | Optional flag + invoice name field |
| Delivery line item | Auto-calculated from vendor policy; vendor can add additional delivery surcharge |

> Maps to PRD FR-6 through FR-15, BR-2, BR-3.

---

## 4. بيانات الأصناف المختلفة / Per-Type Item Data

### 4.1 الأصناف العادية للبيع / Sale Items

**العربية:**

- المجموعة الرئيسية للمنصة (نوعية الحفلة المطلوبة)
- المجموعة الفرعية للمنصة (البنود الرئيسية للحفلة)
- المورد
- المجموعة الرئيسية للمورد
- المجموعة الفرعية للمورد
- الكود
- اسم الصنف
- المقاس
- الوزن
- السن المقترح
- الثيم — إن وجد
- شرح اللعبة — قصير
- شرح اللعبة — طويل
- صور من 1 إلى 11 صورة
- لينك لفيديو
- لينك لصفحة إضافية للصنف

**English:**

| Field | Notes |
|---|---|
| Platform top-level category | Event type group |
| Platform subcategory | Event component group |
| Vendor | FK |
| Vendor's own top-level category | Vendor's internal organization |
| Vendor's own subcategory | |
| Vendor SKU code | |
| Item name | Translatable EN/AR |
| Size | |
| Weight | |
| Suggested age | |
| Theme (optional) | |
| Short description | Translatable |
| Long description | Translatable |
| Images | 1–11 images |
| Video link | |
| Additional info link | External page |

### 4.2 الأصناف التأجيرية / Rental Items

**العربية:** نفس بيانات أصناف البيع، مع إضافة:

- **احتياج الكهرباء** (Yes/No)
- **فترة التأجير العادية** (الافتراضية)

**English:** All sale fields PLUS:

| Extra Field | Notes |
|---|---|
| `requires_electricity` | Boolean |
| `default_rental_duration_hours` | Integer |

> Per locked Tech Decisions §2, the rental detail table additionally captures: outdoor space requirement, min space dimensions, min/max rental duration, setup time, teardown time, security deposit.

### 4.3 الأصناف الديجيتال / Digital Items

**العربية:** هذا القسم تُرك للتحديد لاحقاً في الوثيقة الأصلية (`؟؟؟؟؟`).

**English:** This section was left as TBD in the original brief.

> **Resolved in Tech Decisions §2 + schema:** digital items have `delivery_method` (email/sms/in_app/redirect_url), `has_expiry`, `expiry_days_after_purchase`, `is_refundable_after_delivery`, `redemption_url_template`, `asset_path`, customization fields, custom attributes.

---

## 5. بيانات الصفحة الرئيسية / Homepage Requirements

### العربية

- العمل بلغتين: **عربي / English**
- العميل **Subscription** بـ:
  - الاسم
  - رقم المحمول
  - بريد إلكتروني
  - أو **Facebook** (في هذه الحالة، لا بد أيضاً من كتابة رقم التليفون)
- **Send Link to your friends**
- **Download our Catalogue**
- **البحث بالاسم** — يتم البحث في الأصناف والمجموعات والتصنيفات
- منطقة إعلانات لبعض **الباكدجات (Packages)** للعروض الخاصة
- كشف بكل بنود عيد الميلاد لتذكرة العميل، مع **Link** لكل بند منهم
- **Contact Us**
- **About Us**
- **Terms & Conditions** — التعليمات العامة لحجز عيد ميلاد. كل ما يجب إبلاغ العميل به

### English

| Feature | Phase 1? |
|---|---|
| Bilingual EN/AR throughout | ✅ Yes |
| Sign-up: name + mobile + email, or Facebook OAuth (with phone capture) | ✅ Yes |
| "Send Link to your friends" sharing | ✅ Yes |
| "Download our Catalogue" | ✅ Yes |
| Search across items + groups + categories | ✅ Yes (Meilisearch) |
| Promotional packages banner | ⚠️ Phase 2 — platform-owned packages are deferred |
| Birthday-checklist with deep links per item | ✅ Yes |
| Contact Us / About Us / T&C pages | ✅ Yes (CMS pages table) |

> Note: "Packages" / "Page Slider" are explicitly Phase 2 in the locked Tech Decisions and PRD §11. The promotional banner area can be a placeholder in Phase 1, populated only with Phase-2 content later.

---

## 6. معلومات عامة للمنصة / General Platform Notes

### العربية

- لا بد من وجود **نظام للشات** بين المورد والعميل عبر المنصة، ولكن لا بد من إمكانية متابعة إدارة المنصة.
- يمكن إرسال **صورة أو رسالة صوتية** عبر الشات.
- الشات **لا يسمح بإرسال رقم تليفون أو بريد إلكتروني**.
- **رفع الأصناف** يدوي أو عن طريق شيت Excel ورفع الصور في فولدر واحد لكل مورد.
- وجود **صلاحيات للمورد** لعمل أي تعديل على نظام التشغيل أو في بيانات الأصناف.
- العميل يمكن أن **يدفع المستحقات على أجزاء وبطرق سداد مختلفة**.
- إضافة **نظام نقاط الولاء (Loyalty Points)** وتحديد قيود وطرق استخدام كل مورد لها.

### English

| Feature | Implementation per locked Tech Decisions |
|---|---|
| In-platform chat (customer ↔ vendor) | Firebase Firestore + Reverb metadata mirror in MySQL `chat_message_log` |
| Chat: image + voice note | Supported via Firebase Storage |
| Chat: phone/email blocking | Regex moderation queue job before delivery |
| Admin chat oversight | Platform admin can monitor and freeze threads |
| Manual + Excel bulk upload | Yes, **3 separate Excel templates** (rental, sale, digital), bilingual columns |
| Image upload | Per-vendor folder with filename references in Excel |
| Vendor permissions | Spatie roles + per-product-type vendor scopes (`service.create.{type}.own`) |
| Split payments | Yes — multiple payments per booking |
| Loyalty points | **Per-vendor** (each vendor configures own rules) — locked decision |

---

## 7. طريقة عرض الأصناف / Item Display Order

### العربية

ترتيب ظهور الأصناف:

1. بكود الصنف — **Default**
2. السعر
3. الريفيو
4. تحديد من الإدارة
5. أي تصور آخر

### English

Sort options for catalog browse:

1. Vendor SKU code (default)
2. Price
3. Rating / reviews
4. Admin-curated ordering ("featured")
5. Any other future ordering

---

## 8. البيانات المطلوبة من العميل / Customer Onboarding Questions

### العربية

عبر رسائل فورية من المنصة أو واتساب:

- هل هي أول تعامل مع InstaParty؟
- من أين يعلم بالتطبيق؟ (How did you hear about us?)
- اسم وتاريخ ميلاد الطفل الفعلي
- التقييم العام للمورد وللمنصة

### English

Captured during onboarding via in-app messaging or WhatsApp:

| Question | Storage |
|---|---|
| First time using InstaParty? | `customer_profiles` flag |
| How did you hear about us? | `customer_profiles.how_heard_about_us` |
| Child name + DOB | `customer_profiles.children` JSON |
| Overall vendor rating | `vendor_reviews` |
| Overall platform rating | `app_settings` aggregate / NPS |

---

## 9. ربط هذا المستند بالقرارات المعمارية / Cross-Reference to Architecture

| Brief Concept | Locked Decision |
|---|---|
| Three product types | Tech Decisions §2 — polymorphic base + 3 detail tables |
| Bilingual EN/AR | Bilingual Spec — JSON columns + spatie/laravel-translatable |
| Excel bulk upload | Tech Decisions §12 — 3 templates, no partial commits |
| Chat with admin oversight | Tech Decisions §10 — Firebase + MySQL audit mirror |
| Phone/email blocking | Tech Decisions §10 — moderation queue |
| Multi-payment / split payments | Tech Decisions §11 — Paymob multi-payment |
| Loyalty per-vendor | Tech Decisions schema additions — per-vendor `loyalty_programs` |
| Vendor permissions | Tech Decisions §3 — Spatie + per-type scopes |
| Reviews at service + vendor level | Schema — `service_reviews` + `vendor_reviews` |
| "Packages" promotional area | **Phase 2** — explicitly deferred (PRD §5.2, §11) |
