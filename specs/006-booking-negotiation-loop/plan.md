# Implementation Plan: Booking Negotiation Loop

**Branch**: `006-booking-negotiation-loop` | **Date**: 2026-04-30 | **Spec**: [spec.md](./spec.md)
**Phase**: 3.2 — Booking Negotiation (Week 4–5)

---

## Summary

Implements the vendor negotiation loop for the Booking module: customer submits a draft booking, vendors independently accept/modify/reject their portions, customer reviews modifications with a diff-highlighted view and accepts or rejects, and the loop continues until all vendors are aligned and the booking is confirmed. Builds on top of the Phase 3.1 draft-booking infrastructure already in `app/Modules/Booking/`.

---

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12
**Primary Dependencies**: spatie/laravel-model-states (state machines), Brick\Money (money), spatie/laravel-permission (roles), Filament v3 (admin), Laravel Sanctum (auth)
**Storage**: MySQL 8 (primary), Redis (queue, cache, idempotency key TTL)
**Testing**: Pest (feature + unit)
**Target Platform**: Linux server (Docker), Laravel modular monolith
**Project Type**: Web service API + Filament admin
**Performance Goals**: Standard web API latencies; no special throughput requirements for Phase 1
**Constraints**: Module isolation — no Eloquent model imports across module boundaries; money in integer minor units only; UTC storage, user-TZ at API Resource layer; append-only tables must never be updated except allowed status fields
**Scale/Scope**: Phase 1 — single-region, ~10k bookings/month target

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Rule | Status | Notes |
|---|---|---|
| Three product types covered | ✅ PASS | All three types go through the same negotiation loop; Rental inventory reservation upgrade on confirm |
| Module isolation (no cross-module Eloquent) | ✅ PASS | All new models live in Booking module; Catalog access via existing `CatalogServiceReader` contract |
| Thin controllers (3-line max body) | ✅ PASS | All new controllers delegate to Action classes |
| Fat single-purpose Actions (one execute()) | ✅ PASS | Five new Action classes, each wrapping one use case |
| Money in integer minor units | ✅ PASS | All money stored as `*_minor` BIGINT + `*_currency` CHAR(3); MoneyCast applied |
| Audit trail on state transitions | ✅ PASS | `booking_state_transitions` append-only table; `WriteBookingStateTransitionListener` already wired |
| Idempotency on payment-adjacent endpoints | ✅ PASS | Submit + confirm-modification use `idempotency_keys` table |
| Domain events fire after DB::transaction commit | ✅ PASS | `DB::afterCommit()` pattern followed throughout |
| Append-only tables never softDeleted or UPDATEd | ✅ PASS | `booking_state_transitions`, `booking_snapshots`, `booking_modification_items` are append-only |
| `match($enum)` for cross-type code | ✅ PASS | No if/elseif type strings; type-specific paths use match |
| Per-type product handling | ✅ PASS | Rental reservation upgrade on confirm uses match(ProductType) |
| UTC server timezone | ✅ PASS | All timestamps stored UTC; timezone conversion in API Resources only |

*Post-design re-check: ✅ All gates pass — no violations requiring justification.*

---

## Project Structure

### Documentation (this feature)

```text
specs/006-booking-negotiation-loop/
├── plan.md              ← this file
├── research.md          ← Phase 0 (below)
├── data-model.md        ← Phase 1 (below)
├── quickstart.md        ← Phase 1 (below)
├── contracts/
│   ├── customer-submit.md
│   ├── vendor-actions.md
│   └── customer-modification-decision.md
└── tasks.md             ← Phase 2 (/speckit.tasks — NOT created here)
```

### Source Code Layout

```text
app/Modules/Booking/
├── Database/
│   └── Migrations/
│       ├── [existing: 2026_04_30_000001..000008]
│       ├── 2026_05_01_000009_create_booking_modifications_table.php      [NEW]
│       └── 2026_05_01_000010_create_booking_modification_items_table.php [NEW]
│
├── Domain/
│   ├── Enums/
│   │   ├── [existing] LifecycleStatus, PaymentStatus, FulfillmentStatus, VendorSubStatus
│   │   ├── ModificationStatus.php          [NEW] pending|customer_accepted|customer_rejected|withdrawn|expired
│   │   ├── ModificationProposalKind.php    [NEW] add_item|remove_item|change_quantity|change_price|change_slot|add_surcharge|add_note
│   │   └── ModificationChangeKind.php      [NEW] add|remove|update
│   ├── Events/
│   │   ├── [existing] BookingDraftCreated, BookingItemAdded, BookingItemRemoved
│   │   ├── BookingSubmittedToVendor.php    [NEW]
│   │   ├── VendorAccepted.php             [NEW]
│   │   ├── VendorModificationProposed.php [NEW]
│   │   ├── VendorRejected.php             [NEW]
│   │   └── CustomerModificationDecided.php [NEW]
│   ├── Models/
│   │   ├── [existing] Booking, BookingVendor, BookingItem, BookingAddress,
│   │   │            BookingSnapshot, BookingStateTransition, BookingLock, BookingCustomerNote
│   │   ├── BookingModification.php        [NEW]
│   │   └── BookingModificationItem.php    [NEW]
│   └── Contracts/
│       ├── [existing] BookingRepository, CatalogServiceReader
│       └── (no new contracts needed)
│
├── Application/
│   ├── Actions/
│   │   ├── [existing] CreateBookingDraftAction, AddItemToBookingAction, RemoveItemFromBookingAction
│   │   ├── SubmitBookingAction.php                    [NEW]
│   │   ├── VendorAcceptBookingAction.php              [NEW]
│   │   ├── VendorModifyBookingAction.php              [NEW]
│   │   ├── VendorRejectBookingAction.php              [NEW]
│   │   └── CustomerConfirmModifiedBookingAction.php   [NEW]  (handles both accept+reject)
│   ├── DTOs/
│   │   ├── [existing] CreateBookingDraftDTO, AddBookingItemDTO, ServiceReadDTO
│   │   ├── SubmitBookingDTO.php                       [NEW]
│   │   ├── VendorModifyDTO.php                        [NEW]
│   │   └── CustomerModificationDecisionDTO.php        [NEW]
│   └── Listeners/
│       ├── [existing] RecalculateBookingTotalsListener, WriteBookingStateTransitionListener, WriteInitialBookingSnapshotListener
│       ├── WriteNegotiationSnapshotListener.php       [NEW]  fires on all negotiation events
│       ├── ConfirmInventoryReservationsListener.php   [NEW]  upgrades Rental held→confirmed on BookingConfirmed
│       └── ReleaseInventoryOnCancellationListener.php [NEW]  releases Rental reservations on booking cancelled
│
├── Http/
│   ├── Controllers/
│   │   ├── Customer/
│   │   │   ├── [existing] BookingController, BookingItemController
│   │   │   └── BookingNegotiationController.php   [NEW]  POST submit, POST decide-modification
│   │   └── Vendor/
│   │       └── BookingController.php              [NEW]  POST accept, POST modify, POST reject
│   ├── Requests/
│   │   ├── [existing] CreateBookingDraftRequest, AddBookingItemRequest
│   │   ├── SubmitBookingRequest.php               [NEW]
│   │   ├── VendorModifyRequest.php                [NEW]
│   │   ├── VendorRejectRequest.php                [NEW]
│   │   └── CustomerModificationDecisionRequest.php [NEW]
│   └── Resources/
│       ├── [existing] BookingResource, BookingVendorResource, BookingItemResource
│       └── BookingModificationResource.php        [NEW]
│
├── Filament/
│   └── Resources/
│       └── BookingsMonitorResource.php            [NEW]  view-only, vendor_review+customer_review filter
│
├── Routes/
│   ├── [existing] customer.php
│   ├── vendor.php    [NEW]
│   └── admin.php     [NEW]
│
└── Providers/
    └── [existing] BookingServiceProvider.php      [UPDATE: register new listeners, vendor+admin routes]

tests/Feature/Modules/Booking/
├── [existing] CreateBookingDraftTest, AddItemToBookingTest, RemoveItemFromBookingTest, BookingTotalsTest
├── SubmitBookingTest.php                          [NEW]
├── VendorNegotiationTest.php                      [NEW]  accept, modify, reject
└── NegotiationLoopTest.php                        [NEW]  full loop + idempotency
```

---

## Complexity Tracking

No violations — all design choices follow existing module patterns.

---
