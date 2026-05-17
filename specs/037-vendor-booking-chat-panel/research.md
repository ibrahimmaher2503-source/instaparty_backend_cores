# Research: Vendor Booking Chat Panel (RestrictedChatPanel)

**Feature**: `specs/037-vendor-booking-chat-panel`
**Date**: 2026-05-16
**Branch**: `036-admin-chat-moderation`

---

## Decision 1 — How to embed the chat panel in existing Filament vendor pages

**Question**: The vendor portal pages (`VendorBookingDetailPage`, `VendorBookingDecisionPage`) use `{{ $this->infolist }}` in their Blade templates. What is the correct approach to embed a stateful, interactive chat panel?

**Decision**: Use a dedicated Livewire component (`RestrictedChatPanel`) embedded via `@livewire(...)` in the host page's Blade template. The host page passes `bookingVendorPublicId` as a prop.

**Rationale**:
- Filament Infolist components are read-only by design; they have no native form-submission path, which makes them unsuitable for the compose+send flow.
- A Livewire component is the idiomatic Filament v3 pattern for interactive sub-panels that need their own form and state management.
- The `vendor-booking-detail.blade.php` template (one line: `{{ $this->infolist }}`) already accepts arbitrary HTML/Livewire below the infolist — no framework changes required.
- The component class lives in `app/Modules/Communication/Filament/Vendor/Components/RestrictedChatPanel.php` so ownership stays in the Communication module, not Booking.
- The Blade view lives in `resources/views/vendor-portal/components/restricted-chat-panel.blade.php`.

**Alternatives considered**:
- Filament `Section` with `ViewField` + custom Blade: would require injecting logic into the host page and breaks the module boundary (Booking page would embed Communication business logic).
- REST API + Alpine.js polling: out of scope and contradicts the locked tech stack for vendor portal (Filament/Livewire, not SPA).
- Filament `Widget` attached to the page: Widgets are for dashboard metrics; using them for a full chat surface is semantically wrong and renders in a separate grid container.

---

## Decision 2 — `FirestoreChatGateway` contract extension

**Question**: The existing `FirestoreChatGateway` interface only has `freezeThread()` and `unfreezeThread()`. Does vendor message sending require extending this interface?

**Decision**: Yes. Add `sendMessage(string $firestoreThreadId, string $senderId, string $body): string` to `FirestoreChatGateway`. The return value is the Firestore-generated message ID stored in `chat_message_log.firestore_message_id`. The stub returns a deterministic fake ID (`"stub_msg_".Str::uuid()`).

**Rationale**: The gateway contract is the single abstraction point between the application and Firestore. All Firestore interactions — freeze, unfreeze, send — must flow through it so the stub works identically in test and development environments. Adding to the interface is backward-compatible since there is only one implementation (the stub).

**Alternatives considered**:
- Separate `FirestoreChatSendGateway` interface: unnecessary split; freezing and sending are operations on the same Firestore thread.
- Writing directly to `chat_message_log` without a gateway call: would leave Firestore unsynchronised in production. The stub covers local testing.

---

## Decision 3 — Off-platform pattern extraction point

**Question**: Should `OffPlatformPatternDetector` be authored in spec 036 (admin moderation job) or spec 037 (vendor send action), or as an independent task?

**Decision**: `OffPlatformPatternDetector` is authored **by spec 037** as a shared service. Spec 036's moderation job (`ModerateIncomingChatMessageJob`) will import and use it, not the other way around. If spec 036 ships first, it must leave the regex inline (or in a private method) until this class is extracted.

**Rationale**: The vendor send path is the primary compliance gate (blocks before delivery). The admin moderation job is a secondary sweep (catches what slips through the Firestore client SDK). Authoring the shared class in spec 037 makes the dependency explicit: spec 036 depends on spec 037's service.

**Implementation**:
```text
app/Modules/Communication/Application/Services/OffPlatformPatternDetector.php

Methods:
  detect(string $body): ?OffPlatformMatch   // returns null if clean, or a value object
  normalize(string $body): string           // Eastern Arabic digit normalization

OffPlatformMatch value object:
  flagType: ChatFlagType (Phone | Email | ExternalLink | Other)
  matchedPattern: string
```

**Regex set** (curated in the class docblock, reviewed in ADR-0014):
- Egyptian mobile: `/(?:\+?20|0)?\s*1\s*[0-2,5]\s*\d(?:[\s.\-]*\d){7}/u`
- International E.164: `/\+\d{10,15}/`
- Email: `/[\w.+-]+@[\w-]+\.[\w.-]+/i`
- External link (non-InstaParty): `/https?:\/\/(?!instaparty\.eg)/i`

**Eastern Arabic normalization** maps characters `٠١٢٣٤٥٦٧٨٩` → `0123456789` before applying regexes.

**Alternatives considered**:
- Static config file: makes the regexes harder to unit-test.
- Database-configurable rules: Phase 2 — not in Phase 1 scope.

---

## Decision 4 — Panel state computation location

**Question**: Should the five-state logic (placeholder / open / frozen / closed / system_locked) live inside the Livewire component or in a dedicated value object / service?

**Decision**: Computed in `ChatPanelStateResolver` — a lightweight value object computed from `ChatThread?`, `BookingVendor`, and `Booking`. The Livewire component calls it in `mount()` and caches the result in a `$panelState` property.

**Rationale**: The resolution logic involves two model joins (`booking_vendors.sub_status`, `bookings.lifecycle_status`) and a nullable check (`chat_threads.frozen_at`). Extracting it keeps the Livewire component lean and the logic unit-testable without a full Livewire context.

```text
app/Modules/Communication/Application/Services/ChatPanelStateResolver.php

Method:
  resolve(
    ?ChatThread $thread,
    BookingVendor $bookingVendor,
    Booking $booking
  ): ChatPanelState
```

**Alternatives considered**:
- Method on `ChatThread` model: business logic in models is forbidden by the project constitution.
- Inline in the Livewire component: correct for a small feature, but the state precedence rules are complex enough to merit isolation.

---

## Decision 5 — Short-lived dedup key for network-retry protection

**Question**: The full `idempotency_keys` table pattern (24h TTL) is reserved for payment-path endpoints. What dedup mechanism is appropriate for the 10-second network-retry window?

**Decision**: Use `Cache::put()` with a 10-second TTL keyed on `"vendor_chat_send:{$threadId}:{$vendorProfileId}:{$bodyHash}"`. On a duplicate hit, return the already-persisted `ChatMessageLog` row (fetched by the cached `chat_message_log_id`).

**Rationale**: The `idempotency_keys` table is reserved for monetary mutations per Constitution §VIII. A 10-second Redis TTL is sufficient to absorb Livewire double-click retries and mobile reconnects without burning a permanent DB row. The trade-off (no cross-server dedup after 10 seconds) is accepted and documented in ADR-0014.

**Alternatives considered**:
- Full `idempotency_keys` entry: over-engineered for a text message; reserved for payments.
- No dedup: tolerable for most users but causes visible duplicate messages on flaky connections.

---

## Decision 6 — Model ownership for `ChatMessageLog` and `ChatModerationFlag`

**Question**: Spec 036 is supposed to create `ChatMessageLog` and `ChatModerationFlag` models and migrations. If spec 036 ships first, should spec 037 depend on them? If spec 037 ships first, should it create them?

**Decision**: Spec 036 creates the migrations and models. Spec 037 declares a dependency on spec 036 being merged first. If implementing out of order, spec 037 scaffolds the models as stubs (no migration) and uses `RefreshDatabase` in tests so the spec 036 migrations are loaded transitively.

**Rationale**: The schema locked in `docs/specs/11_DB_Schema.md` (lines 1131–1159) governs both tables. The ordering is: spec 036 creates the tables → spec 037 writes into them. Plan tasks must list the spec 036 dependency check as step 0.

**Alternatives considered**:
- Spec 037 creates its own models without migrations: fragile; would duplicate schema definition.
- Both specs share a common "chat-infrastructure" spec: would require a third spec — over-engineered for Phase 1.

---

## Resolved: No NEEDS CLARIFICATION items remain

All five technical unknowns above are resolved with documented decisions and alternatives. The spec is ready for data-model design.
