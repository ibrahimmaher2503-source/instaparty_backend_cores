# Quickstart: Vendor Booking Chat Panel (spec 037)

**Branch**: `036-admin-chat-moderation`

---

## Prerequisites

Before starting implementation, verify:

```bash
# 1. Spec 036 migrations must be present
php artisan migrate:status | grep chat_message_log
php artisan migrate:status | grep chat_moderation_flags

# 2. ADR-0014 must exist and be Accepted
ls docs/adr/ADR-0014-chat-compliance-admin-oversight.md

# 3. Existing gateway stub is present
ls app/Modules/Communication/Infrastructure/Gateways/FirestoreChatGatewayStub.php
```

---

## Implementation order

Work through `plan.md` layers in order (0 → 9). Each layer is a prerequisite for the next.

### Quickstart commands (after each layer)

```bash
# After Layer 1-2 (enums + contract):
./vendor/bin/phpstan analyse app/Modules/Communication/Domain/

# After Layer 3 (models):
php artisan migrate  # spec 036 migrations must be applied
./vendor/bin/phpstan analyse app/Modules/Communication/Domain/Models/

# After Layer 4 (services):
./vendor/bin/pest tests/Unit/Modules/Communication/OffPlatformPatternDetectorTest.php

# After Layer 5 (action):
./vendor/bin/pest tests/Feature/Modules/Communication/VendorRestrictedChat/SendCleanMessageTest.php
./vendor/bin/pest tests/Feature/Modules/Communication/VendorRestrictedChat/BlockedMessageTest.php

# After Layer 6-7 (Livewire + translations):
php artisan filament:cache-components   # Livewire component discovery

# After Layer 8 (host page integration):
php artisan serve
# Open /vendor/booking-detail/{public_id} and /vendor/booking-decisions/{public_id}
# Verify chat panel renders in EN and AR

# Full test run:
./vendor/bin/pest tests/Feature/Modules/Communication/VendorRestrictedChat/ --group=vendor-chat
./vendor/bin/pest tests/Unit/Modules/Communication/OffPlatformPatternDetectorTest.php

# Code quality:
./vendor/bin/pint
./vendor/bin/phpstan analyse --level=8
```

---

## Key class locations

| Class | Path | Notes |
|---|---|---|
| `SendVendorChatMessageAction` | `app/Modules/Communication/Application/Actions/` | One `execute()` method |
| `OffPlatformPatternDetector` | `app/Modules/Communication/Application/Services/` | Shared with spec 036 |
| `ChatPanelStateResolver` | `app/Modules/Communication/Application/Services/` | Resolves 5-state panel |
| `RestrictedChatPanel` | `app/Modules/Communication/Filament/Vendor/Components/` | Livewire component |
| `ChatPanelState` | `app/Modules/Communication/Domain/Enums/` | Panel state enum |
| `ChatFlagType` | `app/Modules/Communication/Domain/Enums/` | Shared with spec 036 |
| `ChatFlagAction` | `app/Modules/Communication/Domain/Enums/` | Shared with spec 036 |
| `ChatMessageLog` | `app/Modules/Communication/Domain/Models/` | From spec 036 migration |
| `ChatModerationFlag` | `app/Modules/Communication/Domain/Models/` | From spec 036 migration |
| Blade view (component) | `resources/views/vendor-portal/components/restricted-chat-panel.blade.php` | |
| Blade view (detail page) | `resources/views/vendor-portal/pages/vendor-booking-detail.blade.php` | Add `@livewire` call |
| Blade view (decision page) | `resources/views/vendor-portal/pages/vendor-booking-decision.blade.php` | Add `@livewire` call |

---

## Critical invariants (do not violate)

1. `SendVendorChatMessageAction` NEVER updates `chat_message_log.sender_id`, `firestore_message_id`, `message_kind`, or `detected_locale` after insert — only `flagged`, `flag_reason`.
2. Firestore gateway call fires in `DB::afterCommit()` — NEVER inside the transaction.
3. `OffPlatformPatternDetector` is the single source of truth for all regex patterns — no inline copies.
4. Panel compose form is only rendered when `$panelState === ChatPanelState::Open`.
5. Ownership check (`$thread->vendor_profile_id === $vendor->id`) runs in BOTH mount AND every Livewire action call.

---

## Test group tag

All tests in this feature use `->group('vendor-chat')`:

```php
it('sends a clean message', function () {
    // ...
})->group('vendor-chat');
```

Run the group with:
```bash
./vendor/bin/pest --group=vendor-chat
```
