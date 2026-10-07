# TechILO development plan — Digi-Konek

## Baseline

Fresh PHP/MySQL implementation started September 13, 2026. The project is the B2B electronics marketplace Digi-Konek. The planning target is **six calendar weeks (42 days)**, within the requested approximately 1.5-month limit. This is a proposed development plan, not an approved instructor schedule.

The first implementation establishes a complete path from retailer registration and supplier verification to product publication, ordering, supplier decisions, reporting, and audit records. The five-person team can assign named owners after reviewing this baseline; no individual contributions are fabricated here.

## Proposed schedule

| Week | Dates | Focus | Reviewable outcome |
|---|---|---|---|
| 1 | Sep 14–20 | Review this baseline against source SRS/UML; confirm scope and order/stock rules | Accepted requirement-to-feature checklist; local setup working for the team |
| 2 | Sep 21–27 | Account and supplier workflows; profile and catalog improvements | Supplier verification walkthrough; product photo and taxonomy decisions implemented |
| 3 | Sep 28–Oct 4 | Cart editing, order edge cases, inventory reconciliation | Demonstrated complete retailer-to-supplier order workflow |
| 4 | Oct 5–11 | Administration, notifications, reports, audit improvements | Role-based feature review and reporting acceptance |
| 5 | Oct 12–18 | Security, accessibility, browser compatibility, usability, concurrency/load tests | Defect report, fixes, and stakeholder walkthrough |
| 6 | Oct 19–25 | Deployment configuration, backup/restore rehearsal, documentation, final demonstration | Tested demonstration release and updated submission documents |

## Decisions to reconcile with the project documents

1. Pending orders reserve stock immediately. This prevents overselling; acceptance does not reserve it again. Earlier sequence descriptions mentioning inventory deduction at confirmation should be made consistent with this rule.
2. A multi-supplier cart creates separate orders because each supplier owns its own acceptance/fulfillment decision.
3. A retailer can cancel pending orders only. A supplier can accept or reject pending orders and mark accepted orders completed. There is no post-acceptance cancellation/refund workflow yet.
4. The server stores immutable product names and prices on order lines. Product edits do not rewrite history.
5. Report values reflect current statuses of orders created during the selected date range, not payment receipts or completion-date accounting.
6. Supplier approval requires at least one submitted document. The actual authoritative document types and review checklist still need confirmation against the final business requirements.
7. Demo suppliers are preverified fictional fixtures. Seeded prices, stock, and branded specifications are illustrative.

## Remaining work

- Reconcile every FR/NFR identifier with the original source files; the current module mapping is not yet a full requirements acceptance matrix.
- Add product photos and supplier profile details.
- Add quantity editing in the cart. Currently remove/re-add changes quantity.
- Add category/brand CRUD and improved product moderation decisions.
- Decide and implement email verification/password recovery delivery.
- Improve large-list pagination and report export. Current order/audit screens show the latest 200 entries; notifications show 100.
- Add robust admin workflows for resolving pending orders when an account is deactivated.
- Strengthen login throttling across multiple IPs and add monitoring for a public deployment.
- Validate text contrast, touch targets, keyboard flows, screen readers, and browser/device compatibility.
- Rehearse backup/restore, hosting configuration, secrets management, and error monitoring.
- Add migration/versioning support before schema changes are needed; current setup targets an empty database.

## Deliberately excluded

Delivery/logistics, warehouse management, payment gateways, lending, accounting integration, installation, repairs, and warranty claims remain outside this project implementation.
