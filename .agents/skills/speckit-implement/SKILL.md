---
name: speckit-implement
description: Codex CLI port of /speckit.implement for the InstaParty backend. Use when the user asks Codex to "implement the current feature", "execute tasks.md", "run speckit-implement", or asks to start coding from a spec-kit feature folder under specs/NNN-feature-name/. The skill enforces InstaParty's layer order, per-layer commits, API documentation rules, and the Phase-1 / package / Phase-2-feature guardrails.
trigger_keywords:
  - speckit-implement
  - speckit implement
  - implement the feature
  - execute tasks.md
  - run the implementation
  - finish this spec-kit feature
applies_to:
  - InstaParty backend (Laravel 12 + Filament v3)
runtime:
  - Codex CLI (also compatible with any AGENTS.md-aware agent)
version: 1.0.0
---

# Skill: speckit-implement (Codex CLI)

> **Purpose.** A Codex-CLI-flavored port of spec-kit's `/speckit.implement` for the InstaParty backend. Same semantics as the Claude Code slash command, adapted to Codex's tool model and to the inviolable rules in `AGENTS.md` + `.specify/memory/constitution.md`.
>
> **Output contract.** Every layer ends with green `pint` + `phpstan` and a single conventional commit. Nothing is pushed. The active feature's `tasks.md` is the only source of truth for what to do — this skill does not invent tasks.

---

## 0. Preconditions (HARD STOPS — refuse if any fails)

Before doing anything else:

1. `git status --porcelain` MUST be empty for files unrelated to the active feature. If the working tree carries unrelated edits, **stop** and ask the user.
2. The active feature folder under `specs/NNN-feature-name/` MUST contain a `tasks.md`. If missing, **stop** and tell the user to run `/speckit.tasks` (or `speckit tasks` from the CLI) first.
3. The Phase ID declared at the top of `tasks.md` MUST exist in `docs/specs/09_Phasing_Plan.md`. If it doesn't, **stop**.
4. Any package referenced in `tasks.md` that isn't already installed MUST appear in `docs/specs/10_Package_List.md`. If a task says "install X" and X isn't on the list, **stop** and refuse — propose adding to `10_Package_List.md` first.
5. None of the work in `tasks.md` may overlap §4 "Phase 1 Forbidden Features" of `AGENTS.md`. If it does, **stop** and refuse with a citation.

If any precondition fails, do not silently work around it — surface the problem to the user.

---

## 1. Required reading (load in this exact order before generating any code)

Open these in sequence and keep their rules active for the rest of the session:

1. `AGENTS.md` — Codex-facing operating contract (this file's parent contract)
2. `.specify/memory/constitution.md` — spec-kit constitution (governance, principles I-XI)
3. `.specify/memory/project-index.md` — fast index of every spec, ADR, current phase, tech stack, package list
4. `.specify/memory/api-registry.md` — canonical contract for every shipped HTTP route
5. `specs/<active-feature>/tasks.md` — the task list to execute (the only source of truth for WHAT to do)

Then, on demand (do not load preemptively — load when a task references them):

- `docs/specs/01_PRD.md` for FR-NN traceability
- `docs/specs/11_DB_Schema.md` for schema-touching tasks
- `docs/specs/02_Tech_Decisions.md` for architectural questions
- `docs/specs/03_Three_Product_Types.md` for type-aware features
- `docs/specs/04_Bilingual_Spec.md` for translatable fields
- `docs/specs/10_Package_List.md` if a task involves a package
- The relevant `docs/adr/NNNN-*.md` if the task touches a module's bounded context

If `tasks.md` is missing context the skill needs (e.g., a task says "use the PaymentGateway contract" and you don't know its shape), read the implementing module's `Domain/Contracts/` first — never guess.

---

## 2. Layer execution order (NON-NEGOTIABLE)

Group `tasks.md` items into the following 8 layers and execute strictly top-to-bottom. **Skip a layer only if `tasks.md` has zero tasks for it.** Within a layer, follow the order in `tasks.md`. Items marked `[P]` in `tasks.md` may be parallelized only if they touch different files.

| # | Layer | What lives here | Per-layer exit gate |
|---|---|---|---|
| 1 | **Migrations** | `app/Modules/{Name}/Database/Migrations/*.php`, factories, seeders | `php artisan migrate:fresh` succeeds in test env |
| 2 | **Models** | `Domain/Models/*.php` — relationships, casts, scopes ONLY (no business logic) | Model factory → `tinker` → `->save()` works |
| 3 | **Form Requests / DTOs** | `Http/Requests/*Request.php`, `Application/DTOs/*.php` | Validation rules cited from PRD/Schema; both `en` and `ar` keys required for translatable fields |
| 4 | **Actions** | `Application/Actions/{Verb}{Type?}{Noun}Action.php` | One public `execute()`, mutations wrapped in `DB::transaction`, events fire `DB::afterCommit` |
| 5 | **API endpoints + Resources** | `Http/Controllers/*.php` (3-line bodies), `Http/Resources/*.php`, `Routes/{role}.php` | See §4 — every endpoint passes the API documentation gate |
| 6 | **Filament Resources** | `Filament/Resources/*.php` (per-product-type when type-aware) | `php artisan shield:generate --all` after creating, EN+AR tabs, money columns use `->money('EGP', divideBy: 100)` |
| 7 | **Listeners + domain events wiring** | `Domain/Events/*.php`, `Application/Listeners/*.php`, ServiceProvider bindings | Listener registered, queued where applicable, `DB::afterCommit` confirmed |
| 8 | **Pest tests** | `tests/Feature/Modules/{Name}/*.php`, `tests/Unit/...` | Coverage thresholds (constitution §VII): money/auth/bookings 80%+, others 60%+; type-aware features have explicit rental/sale/digital cases |

**Forbidden cross-layer moves:**
- ❌ Writing a model before its migration is committed (ALL of Layer 1 commits before ANY of Layer 2 starts).
- ❌ Writing an API endpoint before its Action exists (Layer 5 needs Layer 4 committed).
- ❌ Writing a Filament Resource before the API contract is stable (Layer 6 needs Layer 5 committed).
- ❌ Skipping Layer 8 to "do tests at the end of the feature." Tests follow the layer they cover.

---

## 3. Per-layer exit ritual (run at the END of every layer)

After the last task in a layer is implemented, run these in order. **Do not commit if any step fails — fix and re-run.**

```bash
# 1. Format
php artisan pint

# 2. Static analysis (must be 0 errors at the configured level)
./vendor/bin/phpstan analyse

# 3. Stage only files that belong to THIS layer (no broad `git add .`)
git add <explicit paths>

# 4. Commit with conventional format
git commit -m "<type>(<module>): <description>"
```

**Commit message format:**

- `<type>` ∈ `feat` (new behavior), `fix` (bug), `chore` (deps/config), `docs`, `test`, `refactor`, `perf`, `style`, `ci`, `build`
- `<module>` is the lowercase owning module (`identity`, `catalog`, `booking`, `payments`, `discovery`, `geography`, `negotiation`, `settlement`, `reviews`, `communication`, `reporting`, `loyalty`, `shared`) — or `meta` for cross-cutting changes
- Description: imperative mood, lowercase, no trailing period
- Examples:
  - `feat(booking): add booking_locks migration with unique active-lock index`
  - `feat(catalog): add CreateRentalServiceAction with afterCommit publish event`
  - `test(payments): cover paymob webhook idempotency under concurrent retries`

**Hard rules during commits:**

- ❌ **Never** run `git push`. Pushing is Ibrahim's call.
- ❌ **Never** use `git commit --amend` on a commit that already exists. Always create a new commit.
- ❌ **Never** use `--no-verify` to skip hooks. Fix the hook failure.
- ❌ **Never** stage `.env`, secret files, `storage/logs/*`, generated docs, or other non-source artifacts.
- ✅ One commit per layer is the default. Two commits per layer is acceptable when a layer has clearly distinct concerns (e.g., schema migration + companion factory + seeder = 1 commit; new model + scope + cast = 1 commit; but rental + sale + digital Actions can each be their own commit if they were tackled sequentially).

---

## 4. API endpoint documentation gate (Layer 5)

Every new HTTP route added in Layer 5 MUST satisfy ALL FOUR of these in the same commit as the route definition. An endpoint without these is incomplete and **cannot be marked `[x]` in `tasks.md`.**

### 4.1 `@bodyParam` PHPDoc on every Form Request field (Scribe-compatible)

```php
/**
 * @bodyParam name object required Translatable name. Example: {"en": "Bouncy castle", "ar": "قلعة قافزة"}
 * @bodyParam name.en string required English name. Example: Bouncy castle
 * @bodyParam name.ar string required Arabic name. Example: قلعة قافزة
 * @bodyParam price_minor integer required Price in piastres. Example: 50000
 * @bodyParam price_currency string required ISO 4217. Example: EGP
 * @bodyParam product_type string required One of rental,sale,digital. Example: rental
 */
class CreateRentalServiceRequest extends FormRequest { ... }
```

Every field listed in `rules()` MUST have a matching `@bodyParam`. Bilingual JSON fields require entries for the parent object AND each locale.

### 4.2 `@response` PHPDoc with realistic EN+AR example data on every API Resource

```php
/**
 * @response 200 {
 *   "data": {
 *     "public_id": "01J9X7K3M2WZE0X8H4Q9N5T7V2",
 *     "name": {"en": "Bouncy castle", "ar": "قلعة قافزة"},
 *     "description": {"en": "Inflatable castle for kids' parties", "ar": "قلعة قافزة لحفلات الأطفال"},
 *     "price_minor": 50000,
 *     "price_currency": "EGP",
 *     "product_type": "rental"
 *   },
 *   "meta": {"locale": "en", "direction": "ltr"},
 *   "errors": []
 * }
 */
class RentalServiceResource extends JsonResource { ... }
```

- `public_id` must be a realistic 26-char ULID.
- Money fields shown as integer minor units paired with `_currency`.
- Translatable fields show BOTH `en` and `ar` values (Arabic must be real Arabic text, not transliteration).
- Always include the `meta` and `errors` keys to mirror the standard `ApiResponse` envelope.
- If the endpoint can return non-2xx documented errors, add a second `@response 4xx { ... }` block.

### 4.3 Update `.specify/memory/api-registry.md`

Append (or update) one row per endpoint. Keep the table sorted by Phase ascending, then Module, then Endpoint path.

| Method | Endpoint | Module | Phase | Auth | Roles | Request Body | Response | Documented |

- `Documented` starts as `📝 partial` after Layer 5; flips to `✅ scribe` after `php artisan scribe:generate` succeeds in §6.
- If the row needs nuance the columns can't carry, add a numbered footnote section under the table — never widen the columns.

### 4.4 Bruno collection entry in `docs/api/collections/{module}.json`

Create the directory and base collection on first endpoint of a module:

```bash
mkdir -p docs/api/collections
```

Per-module Bruno collection file format (`docs/api/collections/{module}.json`) — append one request object per endpoint:

```json
{
  "collection": "InstaParty — {Module}",
  "requests": [
    {
      "name": "Create rental service",
      "method": "POST",
      "url": "{{base_url}}/api/v1/vendor/services/rental",
      "headers": {
        "Accept": "application/json",
        "Accept-Language": "en",
        "Authorization": "Bearer {{vendor_token}}",
        "Idempotency-Key": "{{idempotency_key}}"
      },
      "body": {
        "name": {"en": "Bouncy castle", "ar": "قلعة قافزة"},
        "price_minor": 50000,
        "price_currency": "EGP"
      },
      "assertions": [
        "status == 201",
        "data.public_id != null",
        "data.name.en == 'Bouncy castle'"
      ]
    }
  ]
}
```

If the team uses native `.bru` files instead, mirror the same fields in `.bru` syntax. Either way, every endpoint MUST appear in this directory before Layer 5 is committed.

---

## 5. Per-task bookkeeping

While executing each task:

1. Mark the task `[~]` in `tasks.md` when starting (if your editor/agent supports in-progress markers; otherwise leave `[ ]` until done).
2. Mark the task `[x]` in `tasks.md` the moment it's complete and committed. **Do not batch checks at end of session.**
3. If a task is impossible to complete as written (missing context, contradicts a spec, requires a forbidden package), STOP and ask the user — do not silently rewrite the task.
4. If a task references a contract/interface that doesn't exist yet, propose adding it to the producing module's `Domain/Contracts/` and ask before implementing it speculatively.

---

## 6. Final exit ritual (after the LAST task in `tasks.md` is `[x]`)

Run these in this order. None are optional.

```bash
# 1. Full test suite must pass with --bail (fail fast on first red)
./vendor/bin/pest --bail

# 2. Architecture tests must be green (constitution §VII enforces these)
./vendor/bin/pest --group=architecture

# 3. Confirm every task in tasks.md is [x]
#    If any [ ] or [~] remain, stop and report — do not pretend the feature is done.

# 4. Refresh the api-registry.md "Documented" column to ✅ scribe for endpoints we just shipped
#    (manual edit, then commit as docs(meta): refresh api registry after <feature>)

# 5. Refresh .specify/memory/project-index.md
#    - Update §"Current Phase" to reflect the just-completed phase + next-up phase
#    - Bump the §"docs/adr/" table if a new ADR was accepted in this feature
#    - Bump §"Tech Stack Summary" if a locked-stack item changed (rare — needs ADR)
#    - Bump §"Package List Summary" if a package was added (rare — needs 10_Package_List entry)
#    Commit as docs(meta): refresh project-index after <feature>

# 6. Regenerate API documentation
php artisan scribe:generate

# 7. Final verification commit (only if files in step 4-6 changed)
git status                # confirm only api-registry / project-index / scribe output staged
git add .specify/memory/api-registry.md .specify/memory/project-index.md public/docs/* docs/scribe/*
git commit -m "docs(meta): refresh api registry, project index, and scribe output after <feature>"

# 8. DO NOT PUSH. Print the final git log --oneline -n 20 and hand control back to the user.
```

`knuckleswtf/scribe:^5.9` is approved in `docs/specs/10_Package_List.md` §4 (Dev / Quality). If the binary isn't yet present in `vendor/bin/`, run `composer require knuckleswtf/scribe --dev` once and `php artisan vendor:publish --tag=scribe-config`, then continue. If `composer require` fails (network, lock conflict), surface the error and stop — do not skip step 6 silently and pretend the docs are current.

---

## 7. What this skill refuses to do

- ❌ Push to any remote (`git push`, `git push --force`, etc.).
- ❌ `composer require` a package that isn't in `docs/specs/10_Package_List.md`.
- ❌ Implement a feature listed in §4 "Phase 1 Forbidden Features" of `AGENTS.md`.
- ❌ Skip a layer because "it's small" — the layer order is non-negotiable.
- ❌ Write business logic in a Model.
- ❌ Use `if/elseif` on `product_type` strings (use `match($enum)` with `App\Modules\Catalog\Domain\Enums\ProductType`).
- ❌ Use `float` / `decimal` / `double` for money. Always integer minor units + currency, cast through `MoneyCast`.
- ❌ Expose internal `id` in API URLs (always `public_id` ULID).
- ❌ Fire domain events inside `DB::transaction` (always `DB::afterCommit` or queued listeners).
- ❌ Mark a task `[x]` in `tasks.md` when its commit/tests/docs are not all green.
- ❌ Edit `tasks.md` to remove a task it can't complete — surface the conflict to the user.
- ❌ Run `git commit --amend`, `git rebase -i`, `git reset --hard`, or any history-rewriting command without explicit user instruction.

---

## 8. Output to the user during execution

Keep narration short. After each layer, print exactly:

```
✓ Layer N (<name>) — committed as <type>(<module>): <description>
  pint: ok | phpstan: ok | tests touched: <count>
```

After the final exit ritual, print:

```
✓ Feature <NNN-feature-name> implemented.
  Phase: <Phase ID>
  Layers committed: <N>
  Tasks completed: <X / X>
  api-registry.md rows added: <count>
  Bruno collection updates: <files>
  scribe regenerated: yes/no
  Tests: <pest summary>
  NOT pushed (per AGENTS.md §9).
```

If anything went wrong mid-flight, print a concise blocker report:

```
✗ Blocked at Layer N (<name>).
  Reason: <one sentence>
  Last clean commit: <sha>
  Suggested next step: <one sentence>
```

Never claim success when any of pint / phpstan / pest / task-checkbox is not green.

---

## 9. Codex-CLI specifics

- Codex CLI's tool surface for shell commands matches Bash semantics on Unix; on Windows hosts use forward-slash paths and rely on the project's `bash`/`pwsh` setup. Do not invoke Windows-only binaries.
- Codex's edit tool requires reading a file before editing it — do this implicitly, do not narrate it.
- When Codex offers parallel tool calls, batch independent reads (e.g., the four memory files in §1) into a single round. Sequence anything that depends on the result of a prior read.
- If Codex is configured with a sandbox / permission profile that blocks `git commit`, surface that to the user immediately — do not work around the sandbox.

---

**Companion docs:** `AGENTS.md` (operating contract), `.specify/memory/constitution.md` (governance), `.specify/templates/tasks-template.md` (task format reference). When this skill conflicts with any of those, those win and this skill must be amended.
