# Specification Quality Checklist: Payments — Paymob Gateway + Per-Type Refunds

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-05-02
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs) leaked into business-facing user stories — implementation cues are confined to the Constitution Check, API Endpoints, and Assumptions sections (where they are required by the InstaParty spec-kit constitution)
- [x] Focused on user value and business needs (customer pays, admin refunds)
- [x] Written for non-technical stakeholders in the user-story sections
- [x] All mandatory sections completed (User Scenarios, Requirements, Constitution Check, Success Criteria, Exit Criteria, Cut-List, Assumptions, Dependencies)

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain — every gap was resolvable from `09_Phasing_Plan.md`, `11_DB_Schema.md`, `02_Tech_Decisions.md`, or sensible defaults documented in Assumptions
- [x] Requirements are testable and unambiguous (each FR maps to either a Pest test scenario or an architectural assertion)
- [x] Success criteria are measurable (90s end-to-end payment, 100% bad-signature rejection, exactly-once capture under 100 replays, 10-way concurrency idempotency, all per-type policy boundaries)
- [x] Success criteria are technology-agnostic (no SDK/version mentions outside the locked Paymob choice already in the constitution)
- [x] All acceptance scenarios are defined in Given/When/Then form for all 5 user stories
- [x] Edge cases are identified (concurrent initiates, signature key mix-up, race condition with webhook arrival, currency mismatch, partial-payment refunds rejection, gateway outage, idempotency collision across users)
- [x] Scope is clearly bounded — Phase 4.0 + 4.1 only; Phase 4.2 (Settlement) is named as the next consumer
- [x] Dependencies and assumptions identified (10 assumptions, inbound + outbound dependency lists)

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria — every FR-PAY/FR-REF/FR-IDEM/FR-X traces to one or more user-story Given/When/Then scenarios
- [x] User scenarios cover primary flows (P1: customer pay; P1: webhook capture; P2: rental refund; P2: sale refund; P2: digital refund)
- [x] Feature meets measurable outcomes defined in Success Criteria — SC-001…SC-005 are independently testable
- [x] No implementation details leak into the user-facing parts of the specification (state-machine names, gateway HMAC algorithm, file paths are confined to API Endpoints / Assumptions / Constitution Check)

## InstaParty-specific gates *(extension)*

- [x] Phase ID stated and matches `09_Phasing_Plan.md` (4.0 + 4.1)
- [x] PRD coverage cited (FR-30 + Tech Decisions §11)
- [x] Tables touched cited from Schema §6 (`payments`, `payment_attempts`, `refunds`, `idempotency_keys`, `gateway_webhook_logs`)
- [x] ADR mandated and named (`ADR-0008-payments-module.md`) — flagged as PENDING in Constitution Check VI
- [x] Constitution Check (I–XI) completed with explicit pass/pending/N-A
- [x] Cut-list inherited from `09_Phasing_Plan.md` (split payments, GCC adapters, partial refunds, retry job)
- [x] Exit Criteria checkboxes from the phasing plan (4 for Phase 4.0, 3 for Phase 4.1)
- [x] API endpoints listed with method, path, auth, roles, idempotency, Form Request, Resource
- [x] Three-product-type coverage explicit in refund stories (rental, sale, digital — three Pest groups)
- [x] Bilingual coverage explicit (`reason_notes`, `failure_message` translatable; rejection messages localized)
- [x] Idempotency scope listed (initiate-payment, initiate-refund)

## Notes

- ADR-0008 is the only blocking item before `/speckit.plan` per Constitution VI. Run `/new-module-adr Payments` next.
- Spec-kit constitution mandates the Constitution Check, API Endpoints, and Cut-List sections inside `spec.md` itself — these contain unavoidable technical references and are NOT considered "implementation leakage" for the purpose of the Content Quality checks above.
