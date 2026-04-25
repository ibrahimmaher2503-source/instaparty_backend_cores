---
description: Audit a feature request against Phase 1 scope and push back if it's Phase 2
argument-hint: <feature-description>
---

# Phase 1 scope audit

User wants: **$ARGUMENTS**

## Phase 2 — out of scope (push back)

If the request matches any of these, **say so explicitly** and reference `docs/specs/01_PRD.md` §5.2 and §11:

- Vendor subscription tiers (silver/gold/bronze plans, plan-based privileges, billing lifecycle)
- Platform-owned package products with separate accounting and commission rules
- Dispute-resolution module with compensation / penalty policies
- Different card layout templates per category with admin-managed card schemas
- Vendor page slider module
- Vendor QR / barcode direct catalog module
- Advanced tax invoice workflow beyond basic structural readiness

## Phase 1 — in scope (build it)

These ARE in scope:

- Three product types (rental, sale, digital) with separate detail tables, Form Requests, Actions, Resources, lifecycles, refund policies, Excel templates
- Customer journey: occasion-first → event setup → component selection → submit → vendor review loop → confirm → pay
- Vendor: per-type approval, category-driven service forms, Excel bulk upload, restricted chat during review only, wallet, withdrawals
- Admin: vendor approval, service moderation, booking monitoring, alternative proposing (NEVER auto-replacing on customer's behalf), settlements, content moderation
- Bilingual EN+AR everywhere
- Marketing campaigns via push, WhatsApp, SMS, Mailchimp (integration layer; usage costs are external)
- Loyalty per vendor (each vendor configures own rules)

## Required output

1. State whether the feature is **Phase 1**, **Phase 2**, or **mixed**.
2. If Phase 2: cite the specific PRD §5.2 / §11 line and decline to build it. Offer to scope a Phase 1.5 alternative if reasonable.
3. If mixed: identify which parts are Phase 1 (build now) and which are Phase 2 (defer).
4. If Phase 1: proceed to plan implementation against the locked stack and the three product types.

Don't be silent and don't drift. Ibrahim relies on this guardrail.
