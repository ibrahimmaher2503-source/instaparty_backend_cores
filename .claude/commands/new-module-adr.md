---
description: Generate a new module ADR from the template, pre-filled with module name and date
argument-hint: <ModuleName> (e.g. Catalog, Booking, Payments)
---

# New Module ADR

Generate the ADR for the **$ARGUMENTS** module.

## Required reading first

1. `docs/adr/README.md` — ADR system overview
2. `docs/adr/0001-modular-monolith-pattern.md` — parent pattern
3. `docs/adr/templates/0002-new-module.md` — the template you'll fill in
4. `docs/adr/0003-identity-module.md` — worked example
5. `docs/specs/11_DB_Schema.md` — find the section that lists this module's tables
6. `docs/specs/09_Phasing_Plan.md` — find which phase/week this module belongs to

## Steps

1. **Find the next ADR number.** Run: `ls docs/adr/ | grep -E '^[0-9]{4}' | sort | tail -1` and add 1. Use a 4-digit zero-padded number.

2. **Copy the template** to the new ADR file:
   ```
   docs/adr/{NNNN}-{module-slug}-module.md
   ```
   Use kebab-case for the slug (e.g., `catalog`, `booking`, `payments-and-settlement`).

3. **Replace ALL placeholders** in the new file:
   - `{ModuleName}` → the proper name (e.g., `Catalog`)
   - `0XXX` → the actual ADR number
   - `{module-slug}` → kebab-case slug
   - `YYYY-MM-DD` → today's date
   - All `{...}` blocks in sections 1–11 → real content for THIS module
   - The "delete this top block" instructions → actually delete them

4. **Pull facts from the specs:**
   - Tables owned: from `docs/specs/11_DB_Schema.md` (only the tables this module owns)
   - FK dependencies: from the same schema doc — list every FK that points to OTHER modules
   - Phase + week: from `docs/specs/09_Phasing_Plan.md`
   - Type-aware decision: check `docs/specs/03_Three_Product_Types.md` §15 — is this in the type-aware list?
   - Layer layout: list specific Models, Actions, Filament Resources you'll create
   - Internal decisions: at minimum 2–3 specific tradeoffs for THIS module

5. **Update the ADR index** in `docs/adr/README.md`:
   Add a row to the "Current ADR Index" table.

6. **Wait for Ibrahim's review.** DO NOT write any migrations or code yet. The ADR is the gate — once Ibrahim approves it, then we proceed to `/migrate-module $ARGUMENTS`.

## Output format

After creating the ADR, output:

```
✅ ADR-{NNNN} created: docs/adr/{filename}
✅ Index updated: docs/adr/README.md

Summary:
- Module: $ARGUMENTS
- Phase: {N}
- Tables owned: {count}
- Type-aware: {yes/no}
- Internal decisions: {count}
- Open questions: {count}

⚠️ Waiting for review. Do NOT proceed to migrations until Ibrahim says "go".
```

## Reminders

- **NEVER skip the ADR.** It's the only place where "why" gets recorded.
- **NEVER copy ADR-0003 (Identity) wholesale.** Use the template, not the worked example.
- **If the module doesn't fit cleanly,** flag it in section 11 (Open Questions) — don't paper over it.
- **If you find yourself writing more than 2–3 internal decisions, that's a sign the module is too big.** Suggest splitting it before continuing.
