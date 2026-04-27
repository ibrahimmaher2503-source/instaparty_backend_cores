# ADR-0XXX — {Module Name} Module

> **هذا قالب / This is a template.** انسخه إلى `docs/adr/0XXX-{module-slug}-module.md` واستبدل كل `{...}` بقيم حقيقية.
>
> **Copy to** `docs/adr/0XXX-{module-slug}-module.md` and replace every `{...}` placeholder.
>
> احذف كل القسم العلوي ده بعد ما تنسخ. / Delete this top block after copying.

---

- **Status:** Proposed | Accepted | Deprecated | Superseded by ADR-XXXX
- **Date:** YYYY-MM-DD
- **Decision-makers:** Ibrahim
- **Tags:** module, {phase-tag — e.g., phase-2-catalog}
- **Related:**
  - [`docs/adr/0001-modular-monolith-pattern.md`](0001-modular-monolith-pattern.md) — parent pattern
  - [`docs/specs/01_PRD.md`](../specs/01_PRD.md) §{section}
  - [`docs/specs/02_Tech_Decisions.md`](../specs/02_Tech_Decisions.md) §{section}
  - [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §{section}
  - [`docs/specs/03_Three_Product_Types.md`](../specs/03_Three_Product_Types.md) — *only if module is type-aware*

---

## 1. السياق / Context

**AR:** {2-3 جمل تشرح المشكلة اللي الـ module ده هيحلها. ركز على *ليه* الـ module ضروري دلوقتي، مش ايه. اربطه بـ PRD requirement أو journey step محدد.}

**EN:** {2–3 sentences. What problem does this module solve? Why now? Tie it to a specific PRD FR-XX or journey step.}

**Phase:** {Phase number from `09_Phasing_Plan.md`}
**Built in:** Week {N}, Days {X-Y}

---

## 2. المسؤوليات / Responsibilities

### الـ module ده مسؤول عن / This module owns:

- {Responsibility 1 — e.g., "vendor onboarding lifecycle from registration to per-type approval"}
- {Responsibility 2}
- {Responsibility 3}

### الـ module ده مش مسؤول عن / This module does NOT own:

- {Out-of-scope concern 1 — e.g., "service catalog management" → owned by Catalog module}
- {Out-of-scope concern 2 — e.g., "payment processing" → owned by Payments module}

> **القاعدة:** لو حسيت إنك بتضيف مسؤولية للـ module مش في القائمة فوق، اوقف. اكتب ADR منفصل أو وسع نطاق الـ module بـ ADR amendment.

---

## 3. الجداول المملوكة / Tables Owned

من [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md):

| Table | الغرض / Purpose | Soft-delete? | Append-only? |
|---|---|---|---|
| `{table_1}` | {one-line purpose} | {Yes/No} | {Yes/No} |
| `{table_2}` | {one-line purpose} | {Yes/No} | {Yes/No} |
| `{table_3}` | {one-line purpose} | {Yes/No} | {Yes/No} |

**Foreign key dependencies (jadāwil mawjuda fi modules tania):**

| FK | المرجع / References | Module |
|---|---|---|
| `{table}.{column}` | `{external_table}.id` | `{Module}` |

> **Migration order rule:** لازم migrations الـ modules اللي عندنا FKs ليها تتنفذ الأول. لو ده Identity → محتاج Geography الأول.

---

## 4. هل الـ module type-aware؟ / Is this module type-aware?

اختر واحد / Pick one:

### ☐ Type-aware (يلزم تغطية rental + sale + digital)

الـ module ده فيه per-type behavior. لازم:
- Per-type Form Requests, Actions, API Resources, Filament Resources
- `match($enum)` للـ cross-type code (مفيش `if/elseif`)
- Pest tests تغطي **كل** المنتجات الثلاثة لكل feature type-aware
- استخدام `App\Modules\Catalog\Domain\Enums\ProductType`

**القائمة الكاملة:** Catalog, Booking (booking_items), Imports, Reviews (per-item), Discovery (search facets)

### ☐ Cross-type (مش type-aware)

الـ module ده cross-cutting infrastructure. مفيش رابط مع المنتجات الثلاثة.

**أمثلة:** Geography, Identity (vendor_profiles نفسها), Settlement, Communication infrastructure, Audit, CMS

> **هذا قرار جذري.** لو خطأ، الـ tests كلها هتغلط. تحقق ضد `docs/specs/03_Three_Product_Types.md` §15 قبل ما تختار.

---

## 5. Layer Layout

```
app/Modules/{ModuleName}/
├── Domain/
│   ├── Models/             # {list of models}
│   ├── Enums/              # {if any}
│   ├── Events/             # {domain events fired}
│   ├── States/             # {state machines, if any}
│   └── Contracts/          # {public interfaces other modules consume}
├── Application/
│   ├── Actions/            # {list of Actions}
│   ├── Services/           # {long-running orchestrations}
│   ├── DTOs/               # {data carriers}
│   └── Listeners/          # {event listeners}
├── Infrastructure/
│   ├── Repositories/       # {Eloquent implementations}
│   └── Gateways/           # {external system adapters, if any}
├── Http/
│   ├── Controllers/        # {3-line action body MAX}
│   ├── Requests/           # {Form Requests}
│   ├── Resources/          # {API Resources}
│   └── Middleware/         # {module-specific middleware, if any}
├── Filament/
│   └── Resources/          # {Filament resources}
├── Routes/
│   ├── customer.php        # customer-facing API routes
│   ├── vendor.php          # vendor-facing API routes
│   └── admin.php           # admin-facing routes (rare; Filament covers most)
├── Database/
│   ├── Migrations/
│   ├── Factories/
│   └── Seeders/
├── Resources/
│   └── lang/
│       ├── en/
│       └── ar/
└── Providers/
    └── {ModuleName}ServiceProvider.php
```

---

## 6. القرارات الداخلية / Internal Decisions

> **وثق هنا أي قرار خاص بالـ module:** اختيار library معين، pattern مختلف، tradeoff قبلته.

### 6.1 {Decision Title}

**القرار:** {اللي اخترته}

**البديل:** {اللي رفضته}

**ليه؟:** {2-3 جمل}

### 6.2 {Decision Title}

...

> أمثلة من modules حقيقية:
> - **Identity:** بنخزن TOTP secrets encrypted في `two_factor_secrets`، مش على users — ليه؟ separation of concerns + secret rotation أسهل
> - **Catalog:** Polymorphic base + 3 detail tables، مش STI — ليه؟ راجع [`docs/specs/03_Three_Product_Types.md`](../specs/03_Three_Product_Types.md) §2
> - **Payments:** Custom adapter بدل أي library — ليه؟ Paymob مفيش له SDK رسمي

---

## 7. Inter-Module Communication

### Events بنطلقها / Events we publish:

| Event | When | Payload |
|---|---|---|
| `{ModuleName}\\{EventName}` | {trigger} | {key fields} |

### Events بنستهلكها / Events we consume:

| Event | Source Module | Action |
|---|---|---|
| `{Module}\\{Event}` | `{SourceModule}` | {what we do} |

### Public Contracts (interfaces بنوفرها):

| Contract | الغرض / Purpose | Implementation |
|---|---|---|
| `{ModuleName}\\Domain\\Contracts\\{Name}Repository` | {what other modules use it for} | `Eloquent{Name}Repository` |

### Public Contracts (interfaces بنستخدمها من غيرنا):

| Contract | From Module | Why we need it |
|---|---|---|
| `{Module}\\Domain\\Contracts\\{Name}` | `{SourceModule}` | {usage} |

> **القاعدة:** لو احتجت تقرأ Eloquent model من module تاني → ده signal تطلب Contract منه، مش `use App\Modules\OtherModule\Domain\Models\X`.

---

## 8. Filament Footprint

| Resource | الغرض / Purpose | Translatable? |
|---|---|---|
| `{ModuleName}/Filament/Resources/{Name}Resource.php` | {one-line} | {Yes/No} |

**Navigation group:** `{e.g., "Vendors", "Services", "Bookings"}`

**Permissions generated by Shield:**
- `view_any_{resource}`, `view_{resource}`, `create_{resource}`, `update_{resource}`, `delete_{resource}`
- {Custom permissions, if any: `approve_{resource}`, `moderate_{resource}`, etc.}

> **Reminder:** بعد إنشاء Resources الجديدة، شغل `php artisan shield:generate --all`.

---

## 9. Testing Strategy

**Pest groups:** `{module-name}` {+ per-type groups إذا type-aware: `rental`, `sale`, `digital`}

**Test files location:**
```
tests/
├── Feature/Modules/{ModuleName}/
│   ├── {Feature1}Test.php
│   └── {Feature2}Test.php
└── Unit/Modules/{ModuleName}/
    └── {UnitConcern}Test.php
```

**التغطية المطلوبة / Required coverage:**

- [ ] Happy path
- [ ] Auth (unauthenticated → 401)
- [ ] Authorization (wrong role → 403, wrong vendor → 403)
- [ ] Validation (per-type required fields)
- [ ] Idempotency (لو في endpoint بيكتب)
- [ ] Locale (EN response, AR response)
- [ ] **All three product types** (لو type-aware — non-negotiable)
- [ ] Architecture test: لا توجد imports من modules تانية لـ Eloquent models

**Architecture test pattern:**

```php
test('does not import other module models')
    ->expect('App\Modules\{ModuleName}')
    ->not->toUse([
        'App\Modules\{OtherModule}\Domain\Models',
        'App\Modules\{AnotherModule}\Domain\Models',
    ]);
```

---

## 10. Cut-list (لو تأخرت)

من [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md):

- [ ] {Feature 1 we'll defer if behind} → defer to {Phase 1.5 / W7 / etc.}
- [ ] {Feature 2}
- [ ] {Feature 3}

---

## 11. Open Questions

> **لو في حاجة لسه ما اتقررتش، اكتبها هنا.** مفيش قرار يعتمد لو في open questions.

- [ ] {Question 1}
- [ ] {Question 2}

> **لما يتحلوا، حول كل واحد لـ "Internal Decision" في القسم 6 أو لـ ADR منفصل.**

---

## 12. Implementation Checklist

عند تنفيذ الـ module:

- [ ] Migrations في `{ModuleName}/Database/Migrations/` بالترتيب الصح
- [ ] Models تحت `Domain/Models/` (relationships, casts, scopes ONLY — مفيش business logic)
- [ ] Per-type Form Requests + Actions (لو type-aware)
- [ ] API Resources مع locale conversion
- [ ] Filament Resources (لو فيه admin UI)
- [ ] Service Provider مسجل في `bootstrap/providers.php`
- [ ] Pest tests كاملة per requirement فوق
- [ ] `php artisan shield:generate --all` (لو فيه Filament resources)
- [ ] Translations في `Resources/lang/en/` و `Resources/lang/ar/`
- [ ] Architecture test passes
- [ ] هذا الـ ADR محدّث في القائمة الرئيسية في [`docs/adr/README.md`](README.md)
