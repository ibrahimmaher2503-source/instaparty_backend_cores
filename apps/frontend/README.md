# InstaParty — Customer Frontend (Phase 1)

Next.js 15 App Router + React 19 + Tailwind v4. Drives every visual aspect from the Laravel admin via admin-controlled design tokens, branding, menus, and homepage blocks.

## What's here

| Path | Purpose |
|---|---|
| `src/app/[locale]/layout.tsx` | Server-fetches `/theme/tokens` + `/theme/branding` + 4 menus, injects CSS vars + Google Fonts, sets `dir` for RTL. |
| `src/app/[locale]/page.tsx` | Homepage. Renders the ordered list of admin-defined home blocks via `HomeBlockRenderer`. |
| `src/app/[locale]/search/page.tsx` | Discovery / browse. |
| `src/app/[locale]/services/[publicId]/page.tsx` | Service detail with **per-product-type variants** (rental / sale / digital) selected by discriminated-union switch — never `if/else` on a string. |
| `src/app/[locale]/p/[slug]/page.tsx` | CMS page renderer. |
| `src/app/api/revalidate/route.ts` | Receives `PublicThemeChanged` webhook from Laravel via shared secret — busts ISR. |
| `src/lib/theme.ts` | Token → CSS-variable mapper. |
| `src/lib/api.ts` | Typed server fetchers + browser axios with Sanctum SPA cookies. |

## Pages still to ship

- B.3.2 — `/auth/{login,register,verify,forgot}` (Sanctum SPA cookie flow).
- B.3.3 — `/cart` + `/checkout/{event,items,summary,submit,negotiation,pay,confirmed}`.
- B.3.4 — `/me/{bookings,bookings/[id]/chat,bookings/[id]/review,wishlist,addresses,profile,loyalty,notifications}`.
- B.6 — Playwright E2E suites in EN+AR for all three product types.

## Run

```bash
cd apps/frontend
cp .env.example .env.local
# point NEXT_PUBLIC_API_BASE_URL + API_BASE_URL at your Laravel
pnpm install   # or npm/yarn
pnpm dev
```

The shared `REVALIDATE_SECRET` env var must match Laravel's `services.frontend.revalidate_secret`.
