# Quickstart: Service Moderation Actions + Per-Type Queues

## Prerequisites

- Branch: `024-service-moderation-queues`
- Active feature pointer: `.specify/feature.json` points to `specs/024-service-moderation-queues`
- Run `/speckit.tasks` after this plan before implementation.
- Phase 8.0 ADR exists: `docs/adr/ADR-0013-admin-service-moderation.md`.

## Implementation Checks

1. Confirm `services` schema alignment:

   ```bash
   php artisan migrate:status
   ```

   Required fields/statuses:

   - `status` includes `draft`, `pending_review`, `changes_requested`, `published`, `rejected`, `archived`
   - `moderation_notes` JSON nullable
   - `moderated_at` nullable timestamp
   - `moderated_by` nullable admin/user FK

   Implementation note: schema drift is reconciled by
   `app/Modules/Catalog/Database/Migrations/2026_05_04_000002_align_services_moderation_columns.php`.
   The migration adds the `rejected` lifecycle value, moderation notes, moderator metadata, and a
   `product_type + status` index for pending queue counts.

2. Generate tasks:

   ```text
   /speckit.tasks
   ```

3. Implement in layer order:

   - ADR/schema reconciliation
   - Migration alignment if needed
   - enum/model cast/fillable updates
   - Application actions
   - Filament action adapters
   - pending queue pages
   - navigation badges
   - bulk actions
   - translations
   - Pest tests

## Manual Admin Smoke Test

1. Seed or create one pending service per product type.
2. Open `/admin`.
3. Open Rental Services pending queue.
4. Approve one rental service and confirm it leaves the pending queue.
5. Open Sale Services pending queue.
6. Reject one sale service with EN+AR reason and confirm notes persist.
7. Open Digital Services pending queue.
8. Request edits on one digital service if ADR-0018 service change requests are available.
9. Confirm navigation badges match pending counts for all three resources.
10. Select 50 pending services in one queue and bulk approve.
11. Edit material fields on a published service and confirm it returns to pending review.

## Required Verification Commands

```bash
./vendor/bin/pint
./vendor/bin/phpstan analyse
./vendor/bin/pest --filter=ServiceModeration --bail
./vendor/bin/pest --bail
```

## Expected Test Coverage

- Approve flow updates status, `moderated_by`, `moderated_at`, and fires `ServicePublished`.
- Reject flow persists bilingual notes and fires reject lifecycle signal.
- Request-edits flow delegates to ADR-0018 service change-request actions when available.
- Rental queue never shows sale or digital services.
- Sale queue never shows rental or digital services.
- Digital queue never shows rental or sale services.
- Navigation badge counts are accurate per product type.
- Bulk approve handles 50 services.
- Bulk reject requires EN+AR shared reason.
- Material edits return published rental, sale, and digital services to `pending_review`.
- Non-material edits do not return a published service to review.
