# Frontend tests

## Unit (Vitest)

```bash
pnpm test           # one-shot
pnpm test:watch     # watch mode
```

Coverage:

- `tokensToCss.test.ts` — design-token → CSS-vars mapper + RTL helper
- `formatPriceEGP.test.ts` — Intl-based price formatter + `cn` class merger
- `bookingDraft.test.ts` — zustand booking-draft store invariants (idempotency-key caching, clear, hydration)
- `hrefFromItem.test.ts` — admin-menu → URL dispatcher (internal_path / cms_page / category / occasion / external_url)

## E2E (Playwright)

```bash
pnpm test:e2e             # all suites, both en + ar projects
pnpm test:e2e:ui          # interactive UI mode
pnpm test:a11y            # axe-core only
```

Specs:

- `home.spec.ts` — header renders, language switch flips locale + dir
- `auth.spec.ts` — /me/* redirects to /auth/login?next=… without a token; login renders both tabs
- `theme-switch.spec.ts` — `:root` has `--color-primary-500`; `/ar` ships `--font-arabic`
- `product-types.spec.ts` — surfaces for rental / sale / digital (skips when seed data is absent)
- `booking.spec.ts` — gated by `E2E_RUN_BOOKING=1` + `E2E_TOKEN`, requires seeded backend
- `a11y.spec.ts` — axe-core WCAG 2 A + AA against 6 critical pages

### Configuration

| Env var | Purpose |
|---|---|
| `PLAYWRIGHT_BASE_URL` | Default `http://localhost:3000` |
| `E2E_RUN_BOOKING` | Set to `1` to opt into the booking-wizard suite |
| `E2E_TOKEN` | Sanctum token of a seeded customer (skip auth flow during E2E) |
| `E2E_USER_PHONE` / `E2E_USER_PASSWORD` | Optional, used by auth happy-path specs |

## Lighthouse CI

```bash
pnpm dlx @lhci/cli autorun
```

Reads `lighthouserc.json`: budgets a11y ≥ 0.9 (hard fail), performance ≥ 0.85 (warn). Runs against `pnpm start` after `pnpm build`.
