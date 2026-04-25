# InstaParty — Product Requirements Document (PRD)

> **Phase 1 Baseline + Agreed Client Revisions**
> Document Type: Internal PRD
> Project: InstaParty
> Language: English
> Phase Covered: Phase 1 only
> Exclusions: Phase 2 modules remain out of current build scope

---

## 1. Product Overview

InstaParty is a multi-sided event-commerce platform that connects customers with event vendors and allows customers to discover, configure, review, confirm, and pay for event bookings through web and mobile channels. The platform also provides a dedicated vendor experience and a full administrative control panel for moderation, operations, payments, communications, and reporting.

This PRD is the implementation baseline for the development team. It consolidates the previously agreed business and technical scope, plus the latest client comments accepted into Phase 1 before coding starts.

## 2. Product Deliverables

- Customer-facing website (front-end web experience)
- Customer mobile application for Android and iOS
- Separate vendor mobile application
- Back-end APIs and complete admin control panel

## 3. Technology Baseline

| Layer | Technology |
|---|---|
| Back-end | Laravel — central business logic, APIs, admin workflows, permissions, queues, notifications, settlements |
| Database | MySQL / MariaDB |
| Caching / background jobs | Redis + queues |
| Customer web front-end | Modern SPA / SSR-ready front-end integrated with Laravel APIs |
| Mobile apps | Cross-platform mobile stack for Android and iOS |
| Phase 1 third-party integrations | Push notifications provider, WhatsApp API provider, SMS gateway, email campaign service such as Mailchimp |

## 4. User Roles

| Role | Primary Responsibility | Core Capabilities |
|---|---|---|
| **Customer** | Create and manage event orders | Browse, discover vendors, request bookings, review changes, confirm and pay, receive notifications |
| **Vendor** | Provide products and services | Register, manage services, review incoming requests, modify / accept / reject, execute orders, manage wallet |
| **Admin** | Operate and govern the platform | Approve vendors and services, monitor orders, configure rules, oversee communications, settlements, campaigns, and reports |

## 5. Phase 1 Scope Summary

### 5.1 Included in Phase 1

- Customer journey starts with **occasion type selection** as the first operational step
- Two discovery directions: **broad browsing** and **guided selection**. Presentation logic should move closer to package-style recommendations without removing the underlying structure
- Vendor can modify orders by adding line items, changing price, adjusting slot time, and writing explanatory notes
- Any vendor-side modification must be **highlighted clearly** to the customer before final approval
- **Event time range** is the default base slot for the full booking; individual short-duration services can have their own operational start time
- A notification appears when the customer selects a service whose duration is shorter than the full event duration, prompting the customer to set the service start time
- Vendor service creation flow must be **category-first**; required fields are displayed dynamically based on the chosen group/category
- Marketing communication integrations: push, WhatsApp, SMS, and email (via Mailchimp or similar)
- **Bulk upload for vendor services through Excel templates** from Day 1 of the build
- Admin **does not** choose replacement vendors on behalf of the customer; admin only monitors and facilitates the process

### 5.2 Deferred to Phase 2

- Vendor subscription module (silver / gold / bronze plans, plan-based privileges, billing lifecycle)
- Platform-owned package products with separate accounting and commission rules
- Dispute-resolution module with compensation / penalty policies
- Different card layout templates per category with admin-managed card schemas
- Vendor page slider module
- Vendor QR / barcode direct catalog module
- Advanced tax invoice workflow beyond basic structural readiness

## 6. High-Level User Journeys

### 6.1 Customer Journey

1. Enter platform
2. Select occasion type first
3. Set location, date, event time range, and relevant attributes (e.g., child age / gender, theme, if applicable)
4. Browse vendors / services or review recommended package-style options generated from the chosen event context
5. Select required event components
6. Review event summary
7. Submit booking request for vendor review
8. Receive one of three outcomes:
   - Full approval
   - Modified proposal
   - Rejection of some items
9. Review highlighted changes or choose alternatives where needed
10. Confirm final version and pay the required amount
11. Receive booking confirmation and ongoing notifications

### 6.2 Vendor Journey

1. Register account and submit required company / personal / banking details
2. Wait for admin approval
3. Create and manage services using **category-driven forms**
4. Set coverage areas, availability, pricing, and operational constraints
5. Receive booking requests
6. Accept, modify, or reject requests
7. If modified, provide additional line items, adjusted pricing, revised slot times, and explanatory notes
8. After customer payment and confirmation, execute the service and update status
9. Receive earnings into wallet after settlement logic is applied
10. Request withdrawal of available balance

### 6.3 Admin Journey

1. Approve vendors and review vendor profile changes
2. Approve service additions or material service edits where approval is required
3. Monitor operational order flow and vendor response times
4. Facilitate customer/vendor process **without** selecting replacement vendors on behalf of customers
5. Run marketing campaigns using integrated third-party services
6. Handle settlements, wallet movements, and withdrawal approvals
7. Moderate reviews, manage platform settings, and monitor logs and reports

## 7. Functional Requirements

### 7.1 Customer Discovery and Entry

- **FR-1:** The first required customer step after entry is occasion type selection
- **FR-2:** The system must collect event context data early — location, date, overall event time range, and other relevant filters
- **FR-3:** The system must support broad browsing of vendors/services
- **FR-4:** The system must also present package-like recommendations based on event context without requiring a full architectural replacement of the current flow

### 7.2 Service Selection and Booking Draft

- **FR-5:** Customers can assemble a booking composed of multiple services and/or products from multiple vendors
- **FR-6:** The event time range acts as the default slot for the booking
- **FR-7:** The system must support special services that operate for shorter durations within the event
- **FR-8:** When a short-duration service is selected, the system must prompt the customer to choose its operational start time
- **FR-9:** The booking summary must be shown before submission

### 7.3 Vendor Review Cycle

- **FR-10:** Submitted booking requests must be routed to all selected vendors
- **FR-11:** Vendors must be able to accept, modify, or reject their relevant portions
- **FR-12:** Vendor modifications may include new line items, item description, extra price, revised slot time, revised quantity, additional notes, or operational surcharges where allowed
- **FR-13:** Any modified item must be **visually highlighted** for the customer
- **FR-14:** Customers must explicitly re-review and re-approve modified bookings
- **FR-15:** The booking may loop between customer and vendor until final alignment is reached

### 7.4 Admin Intervention Rules

- **FR-16:** Admin may monitor and follow up on stalled workflows or late vendor responses
- **FR-17:** Admin may communicate and facilitate, but Phase 1 must not enforce admin-selected replacement vendors
- **FR-18:** The customer remains the final selector of alternatives when the original vendor or item is unavailable

### 7.5 Vendor Service Management

- **FR-19:** Vendors must create services by choosing a group/category first
- **FR-20:** The service form must render **category-specific required fields dynamically**
- **FR-21:** Vendors must be able to manage prices, descriptions, images, availability, and coverage areas within the rules defined by admin
- **FR-22:** **Bulk upload through Excel templates must be supported in Phase 1**; template structure may differ by category (and by product type per locked Tech Decisions)

### 7.6 Marketing Campaign Integrations

- **FR-23:** Admin must be able to define push campaigns through an integrated external provider
- **FR-24:** Admin must be able to define WhatsApp campaigns through an integrated external provider
- **FR-25:** Admin must be able to define SMS campaigns through an integrated external provider
- **FR-26:** Admin must be able to define email campaigns via an integrated email marketing service such as Mailchimp
- **FR-27:** The product must separate platform development scope from external provider usage fees, message credits, and subscription costs

### 7.7 Wallet and Finance

- **FR-28:** Vendors must have wallet visibility showing earnings, deductions, and available balance
- **FR-29:** Admin must be able to review and approve withdrawal requests
- **FR-30:** Settlement logic must support commission deduction and booking-related financial tracking for Phase 1 baseline operations

## 8. Business Rules

- **BR-1:** Occasion type is mandatory before meaningful service discovery can proceed
- **BR-2:** Booking-level event time range is the default slot unless an item-level override is explicitly required
- **BR-3:** Any vendor-originated modification requires customer review before booking confirmation
- **BR-4:** Admin can monitor and facilitate but does not choose vendor alternatives on behalf of the customer in Phase 1
- **BR-5:** Category determines the schema of vendor service data entry
- **BR-6:** Third-party messaging and campaign channels are integrated technically, but their usage costs are external to core development pricing

## 9. Non-Functional Requirements

- **English and Arabic** content support at application level, with proper handling for both **RTL and LTR** interfaces where required
- Clean **permission model** across customer, vendor, and admin roles
- **Queue-based processing** for background communication and campaign dispatch
- **Auditability** for key workflow events, especially booking changes and settlement actions
- Scalable API structure to support website, customer app, vendor app, and admin panel from the same core back-end
- **Validation-driven import process** for Excel bulk upload, with category-specific templates and clear import error feedback

## 10. Integrations and External Dependencies

Phase 1 will include the technical integration layer for external communication providers. The development team must build these integrations in a provider-agnostic way where possible.

| Integration | Purpose | Notes |
|---|---|---|
| Push provider | Push notification campaigns | Provider to be finalized; implementation should allow environment-based credentials and configurable templates |
| WhatsApp API | WhatsApp campaigns / notifications | Message templates and policy restrictions depend on external provider |
| SMS gateway | SMS campaigns / notifications | Gateway selection and usage credits are external operational costs |
| Mailchimp or equivalent | Email campaigns | Use API-based list, template, and campaign sync |
| Payment gateway | Booking payment processing | Final gateway specifics may vary by geography and commercial agreement (Paymob locked for Phase 1 per Tech Decisions §11) |

## 11. Phase 1 Exclusions / Not in Current Build

- Vendor subscription package engine
- Platform-owned productized packages with separate accounting treatment
- Formal dispute-resolution engine with automated penalties or compensation rules
- Per-category visual card-template system managed by admin
- Vendor page slider
- Vendor QR / barcode direct catalog feature
- Advanced tax invoice automation beyond foundational readiness

## 12. Delivery Notes for Engineering

- All documentation, flows, and build assumptions must follow this updated PRD rather than earlier baseline drafts where conflicts exist
- Accepted client comments are considered part of Phase 1 scope only where they appear in this document
- Deferred items must not be partially implemented unless specifically approved as a scoped change request
- Development should begin only after internal alignment between product, design, backend, frontend, and QA on this document

---

**End of PRD**
