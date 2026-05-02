# Architecture Decision Records (ADR) — InstaParty

> **AR:** سجل القرارات المعمارية — كل قرار له ملف منفصل، مرتب رقمياً.
> **EN:** Architecture Decision Log — one file per decision, numbered sequentially.

---

## ليه ADRs؟ / Why ADRs?

**AR:** الـ specs (PRD, Tech Decisions, Schema) بتقول "إيه" المشروع. الـ ADRs بتقول "ليه" اخترنا الطريقة دي. لما تيجي بعد 6 شهور وتسأل "ليه عملنا polymorphic base + 3 detail tables بدل Single Table Inheritance؟" — الإجابة في ADR، مش في commit message.

**EN:** Specs say *what* the project is. ADRs say *why* we chose the path we did. Six months from now, when you (or a future contributor) asks *"why polymorphic base + 3 detail tables instead of STI?"* — the answer lives in an ADR, not a commit message.

---

## متى تكتب ADR / When to write an ADR

اكتب ADR عندما:

- تتخذ قرار معماري جديد (نمط، library، schema decision)
- تنشئ module جديد في `app/Modules/`
- تعكس قرار سابق
- تختار بين بدائل متكافئة وتحتاج توثيق السبب

**لا تكتب ADR عندما:**

- بتعمل feature عادي يطبق pattern موجود
- بتصلح bug
- بتعدل copy/translation/wording

---

## النظام الرقمي / Numbering

- **ADR-0001 إلى 0099:** قرارات Phase 1 (الـ baseline architecture)
- **ADR-0100 إلى 0199:** قرارات Phase 1.5 (mobile + web)
- **ADR-0200+:** قرارات Phase 2

كل ADR رقمه ثابت. لو تم عكسه، يفضل في الفولدر بحالة `Superseded` ويتلاحق بـ ADR جديد.

---

## الحالات / Statuses

| Status | المعنى |
|---|---|
| `Proposed` | مقترح — لسه في النقاش |
| `Accepted` | معتمد ومطبق |
| `Deprecated` | لسه شغال لكن مش هيتم استخدامه في الكود الجديد |
| `Superseded by ADR-XXXX` | تم استبداله بقرار جديد — يجب الإشارة للـ ADR البديل |

---

## كيفية إنشاء ADR جديد / How to create a new ADR

### للـ module جديد (الأكثر شيوعاً):

```bash
# 1. خد آخر رقم + 1
ls docs/adr/ | sort | tail -1

# 2. انسخ template الـ module
cp docs/adr/templates/0002-new-module.md \
   docs/adr/0XXX-{module-name}-module.md

# 3. عدل الملف، استبدل {placeholders}، احفظ
```

### للـ ADR عام (مش module):

```bash
cp docs/adr/templates/0001-generic-decision.md \
   docs/adr/0XXX-{decision-slug}.md
```

---

## القائمة الحالية / Current ADR Index

| # | العنوان | Status | المرجع |
|---|---|---|---|
| 0001 | Modular Monolith Pattern | Accepted | [`0001-modular-monolith-pattern.md`](./0001-modular-monolith-pattern.md) |
| 0002 | (Template للـ modules الجديدة) | Template | [`templates/0002-new-module.md`](./templates/0002-new-module.md) |
| 0003 | Identity Module (نموذج محلول) | Accepted | [`0003-identity-module.md`](./0003-identity-module.md) |
| 0004 | Catalog Module | Accepted | [`0004-catalog-module.md`](./0004-catalog-module.md) |
| 0005 | Payments Module | Accepted | [`0005-payments-module.md`](./0005-payments-module.md) |

> **هذا الجدول لازم يتحدث يدوياً مع كل ADR جديد.** Claude Code هيصرّ على تحديثه كجزء من spec-guard hook (لو فعلت ده).

---

## Slash Command (اختياري)

عندك في `.claude/commands/`، ممكن تضيف `/new-module-adr {ModuleName}` يولد لك ADR محلول جزئياً تلقائياً. لو عاوز ده، اطلبه وهضيفه.

---

## ملاحظات مهمة / Important Notes

- ADRs **مش specs**. ALL ADRs ≠ Tech Decisions. الـ Tech Decisions ملف واحد عملاق فيه القرارات النهائية. الـ ADRs هي الـ trail بتاع التفكير اللي وصلنا للقرارات دي.
- **ADRs immutable بعد ما تعتمد.** لو غيرت رأيك، اكتب ADR جديد يلغي القديم — ما تعدلش القديم.
- Claude Code يقرأ من `docs/adr/` لما تسأله عن قرار معماري. خلي الملفات قصيرة ومركزة عشان يلاقي الإجابة بسرعة.
