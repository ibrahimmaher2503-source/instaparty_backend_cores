# ADR-0001 — Modular Monolith Pattern

- **Status:** Accepted
- **Date:** 2026-04-15 (locked at project kickoff)
- **Decision-makers:** Ibrahim
- **Tags:** architecture, foundation
- **Related:** [`docs/specs/02_Tech_Decisions.md`](../specs/02_Tech_Decisions.md) §1

---

## السياق / Context

**AR:** InstaParty عبارة عن multi-sided marketplace فيه 3 أنواع من المنتجات (rental, sale, digital)، ثلاث جهات (customer, vendor, admin)، ولازم يخدم web (Next.js) + mobile (Flutter) + admin (Filament) من نفس الـ backend. الـ scope الكلي ~60 جدول × 13 modul. التطوير solo، 8 أسابيع للـ Phase 1.

**EN:** InstaParty is a multi-sided marketplace with 3 product types, 3 user roles, serving web + mobile + admin from one Laravel backend. Phase 1 is ~60 tables across ~13 logical modules, built solo in 8 weeks.

السؤال: إزاي ننظم الـ codebase؟

## البدائل المعتبرة / Alternatives Considered

### 1. Flat Laravel structure (الـ default)

```
app/
├── Models/
├── Http/Controllers/
├── Services/
└── Actions/
```

**المميزات:** أسرع للبدء، Laravel docs بتفترض ده.
**العيوب:** عند 60 جدول و 13 سياق منطقي، الـ folders بتبقى كومة ملفات. اتخاذ قرار "هل ده شغل Booking ولا Catalog؟" بيتحول لـ guesswork. Cross-context dependencies بتبقى invisible.

### 2. Microservices

كل module في repo منفصل، بـ API بينهم.

**المميزات:** عزل قوي، scale مستقل لكل service.
**العيوب:**
- Solo dev × 8 weeks = إستحالة. Network latency, distributed tracing, deploy orchestration كلها overhead.
- مفيش volume يبرر الانفصال (طلبات الحفلات ليست Uber).
- عقد الـ deploy وdebugging أضعاف الـ benefit.

### 3. Modular Monolith ← المختار

كود في deploy واحد، لكن منظم في modules معزولة منطقياً.

```
app/Modules/
├── Identity/
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   ├── Http/
│   ├── Filament/
│   ├── Routes/
│   ├── Database/Migrations/
│   └── Providers/
├── Catalog/
├── Booking/
├── ...
```

## القرار / Decision

**Modular Monolith** بالـ shape التالي:

1. كل module تحت `app/Modules/{Name}/` بنفس الـ layer structure (Domain → Application → Infrastructure → Http → Filament).
2. Module communication عبر **domain events** والـ **public Contracts** فقط — مفيش `use App\Modules\Booking\Domain\Models\Booking` من داخل Catalog.
3. Module migrations في `Modules/{Name}/Database/Migrations/`، مش root `database/migrations/`.
4. كل module عنده `ServiceProvider` بيسجل migrations + translations + routes + listeners.
5. Laravel events بـ `DB::afterCommit()` — مش inside transaction.

## العواقب / Consequences

### إيجابية / Positive

- **Refactor-friendly:** لو الحجم كبر، استخراج module للـ microservice مهمة محدودة (الـ Contracts موجودة بالفعل).
- **Cognitive load أقل:** "هل ده شغل Catalog؟" → روح `app/Modules/Catalog/` وخلاص.
- **Module-level testing:** Pest groups (`->group('catalog')`) بتشغل tests الـ module بتاعك بس.
- **Filament auto-discovery** بيشوف الـ resources من كل module تلقائياً.

### سلبية / Negative

- **Boilerplate لكل module جديد:** Service provider، 4 route files، translations folder. **حل:** ADR template (هذا الملف وأخواته) + slash command.
- **Laravel docs مش بتفترض الترتيب ده.** المطورين الجدد محتاجين توجيه. **حل:** `CLAUDE.md` + `.claude/rules/modules.md`.
- **Easier to violate isolation than enforce it.** **حل:** Pest arch test بـ pattern: `expect('App\Modules\Catalog')->not->toUse('App\Modules\Booking\Domain\Models')`.

## مرجع التنفيذ / Implementation Reference

- **التفاصيل الكاملة:** [`docs/specs/02_Tech_Decisions.md`](../specs/02_Tech_Decisions.md) §1
- **Module rules:** [`.claude/rules/modules.md`](../../.claude/rules/modules.md)
- **Layout reference:** [`CLAUDE.md`](../../CLAUDE.md) → Module Layout

---

## ملاحظة / Note

كل module جديد لازم يكون عنده ADR بيوصف نطاقه ومسؤولياته وحدوده. استخدم [`templates/0002-new-module.md`](templates/0002-new-module.md) كقالب.
