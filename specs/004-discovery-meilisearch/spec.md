# Feature Specification: Discovery — Meilisearch Search & Wishlists

**Feature Branch**: `004-discovery-meilisearch`
**Created**: 2026-04-30
**Status**: Draft
**Phase**: 3.0 — Week 4
**PRD Coverage**: FR-3, FR-4

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Bilingual Full-Text Search (Priority: P1)

A customer types "نطاطية" (Arabic for "bouncy castle") into the search bar. The platform searches across Arabic-language service names and descriptions, returning rental services that match. The same search in English ("bouncy castle") returns the same results. Results are paginated.

**Why this priority**: Search is the primary discovery mechanism. Without it, customers cannot find services from any vendor.

**Independent Test**: A customer can search for a term in Arabic and receive a non-empty, relevant, paginated result set.

**Acceptance Scenarios**:

1. **Given** published rental services with Arabic names containing "نطاطية", **When** a customer searches `q=نطاطية`, **Then** those services appear in the first result page.
2. **Given** services indexed in both EN and AR, **When** a customer searches in English, **Then** English-name matches are returned.
3. **Given** a search with no matching services, **When** the customer submits the query, **Then** an empty result set is returned with zero total count and no error.

---

### User Story 2 — Faceted Filtering by Product Type and Occasion (Priority: P1)

A customer searches for birthday rentals. They apply a `type=rental` filter and select the "birthday" occasion. Only rental services tagged with the birthday occasion appear, regardless of other matching services.

**Why this priority**: Type and occasion filters are the two most important discovery dimensions; without them customers see irrelevant results.

**Independent Test**: Applying a `type=rental` filter returns only rental-type services; applying `occasion=birthday` returns only services tagged with that occasion.

**Acceptance Scenarios**:

1. **Given** a mix of rental, sale, and digital services, **When** a customer filters `type=rental`, **Then** only rental services appear in results.
2. **Given** services with various occasion tags, **When** a customer filters `occasion=birthday`, **Then** only services associated with that occasion appear.
3. **Given** both filters applied, **When** the customer searches, **Then** results are the intersection of both filters.
4. **Given** a price range filter applied, **When** the customer searches, **Then** only services within that price range appear.

---

### User Story 3 — Wishlist Management (Priority: P2)

An authenticated customer finds a service they like and adds it to their wishlist. They can later view their wishlist and remove services they are no longer interested in. Unauthenticated users cannot access wishlists.

**Why this priority**: Wishlists drive return visits and eventual conversions. They depend on search results but are not needed for the core search flow.

**Independent Test**: An authenticated customer can add a service to their wishlist and verify it appears in their wishlist listing; removing it causes it to disappear.

**Acceptance Scenarios**:

1. **Given** an authenticated customer, **When** they add a service to their wishlist, **Then** the service appears in their wishlist.
2. **Given** a service already in the wishlist, **When** the customer removes it, **Then** it no longer appears.
3. **Given** an unauthenticated request to add a wishlist item, **When** the request is submitted, **Then** a 401 Unauthorized response is returned.
4. **Given** a customer tries to add the same service twice, **When** the request is submitted, **Then** only one wishlist entry exists (idempotent).

---

### User Story 4 — Admin Re-index on Demand (Priority: P3)

An admin user can trigger a full re-index of all published services from the admin panel without taking the search system offline. New index replaces old index atomically.

**Why this priority**: Operational necessity for recovery from index drift; low frequency but critical when needed.

**Independent Test**: Admin triggers re-index; after completion, new or updated services appear in search that were missing before.

**Acceptance Scenarios**:

1. **Given** a newly published service not yet in the search index, **When** the admin triggers re-index, **Then** the service appears in search results afterward.
2. **Given** the re-index is in progress, **When** a customer performs a search, **Then** they still receive results (no downtime).

---

### Edge Cases

- What happens when a customer searches with a query containing only whitespace? → Return empty results gracefully.
- How does the system handle a service being unpublished while it is in a wishlist? → Service remains in wishlist data but is excluded from search; wishlist view may show it as "unavailable."
- What happens when an item is added to a wishlist and the service is later deleted? → Wishlist entry is retained; service display degrades gracefully.
- What happens when pagination parameters are out of range (e.g., `page=9999`)? → Return empty results with correct total count, no error.
- What happens when a vendor has no services indexed yet? → Filter by `vendor_id` returns empty results gracefully.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST index all published services with bilingual searchable fields (EN and AR) for names and short descriptions.
- **FR-002**: System MUST support full-text search queries in both English and Arabic, using locale-appropriate tokenization.
- **FR-003**: System MUST support filtering results by `product_type` (rental, sale, digital) as a facet.
- **FR-004**: System MUST support filtering by `category_id`, `occasion_ids`, `vendor_id`, and `price_minor` range.
- **FR-005**: System MUST return paginated search results with total count and current page metadata.
- **FR-006**: System MUST return search results in the customer's requested locale (EN or AR field values).
- **FR-007**: Authenticated customers MUST be able to add a published service to their wishlist.
- **FR-008**: Authenticated customers MUST be able to remove a service from their wishlist.
- **FR-009**: Wishlist add MUST be idempotent — adding the same service twice produces one entry.
- **FR-010**: Unauthenticated requests to wishlist endpoints MUST return 401.
- **FR-011**: Admin users MUST be able to trigger a full re-index of all published services on demand via the admin panel.
- **FR-012**: Re-index MUST be zero-downtime (swap index, do not take search offline).
- **FR-013**: System MUST append a record to `search_logs` for each search query (append-only, no PII beyond user_id).
- **FR-014**: Search index MUST be updated automatically when a service is published, updated, or archived.

### Key Entities

- **Service (indexed)**: A published service record with bilingual name, short description, product type, category, occasion tags, vendor, and base price.
- **Wishlist**: A per-customer collection of saved services; one wishlist per customer.
- **Wishlist Item**: A link between a wishlist and a service; has a created timestamp.
- **Saved Search**: A named search query a customer can recall (schema only in Phase 3.0; UI deferred).
- **Search Log**: An append-only record of a search query event: query string, filters applied, result count, user id, locale, timestamp.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Customers searching in Arabic receive relevant results within 1 second for the first result page under normal load.
- **SC-002**: Applying a `product_type` filter reduces the result set to only that type with 100% accuracy (zero cross-type leakage).
- **SC-003**: Wishlist add and remove operations are reflected immediately (next read returns updated state).
- **SC-004**: Admin-triggered re-index completes without any customer-facing search downtime.
- **SC-005**: All three product types (rental, sale, digital) appear in search results and are independently filterable.
- **SC-006**: Pagination metadata (total, current page, per-page count) is accurate for all result sets.

---

## Assumptions

- The `services` table and its three detail tables (`service_rental_details`, `service_sale_details`, `service_digital_details`) already exist from Phase 2.0 (Catalog). This phase adds Scout/Meilisearch on top.
- Meilisearch is available in the development environment (Docker service) and production (managed or self-hosted). Configuration is environment-variable-driven.
- "Published" services are the only ones indexed. Draft, pending_review, and archived services are excluded from the index.
- A customer has exactly one wishlist created on first use (lazy creation).
- Saved searches are migrated and schema-ready in Phase 3.0, but the UI and recall endpoint are deferred to Phase 1.5 (cut-list).
- Search logs are append-only; no update or delete operations are permitted on `search_logs`.
- The admin re-index action uses Meilisearch's index swap feature to achieve zero downtime.
- Arabic tokenization relies on Meilisearch's built-in Unicode/Arabic language support; no external NLP service is required in Phase 3.0.
- Price filtering uses `price_minor` (integer piastres) for comparison; no currency conversion.
- The `occasion_ids` facet stores an array of occasion IDs on the indexed document; many-to-many relationship is flattened at index time.
