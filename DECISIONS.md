## Release script safety

- Release must run only from a clean git workspace
- Release script must never auto-commit all pending changes
- Release ZIP contains the full plugin, not only changed files
- Local backup/archive artifacts must be excluded from packaging

Why:
- prevents accidental commits of unrelated work
- keeps releases reproducible and safe for deployment

## Customer sync after admin request edit

- Request edits must re-sync current customer data
- Customer identity remains the current email on the request
- Changing email creates/updates by the new email only
- Old customer records are not merged automatically in this phase

Why:
- keeps customer table aligned with edited requests
- avoids premature complexity around merges and identity reconciliation

## First admin customers overview

- The first customers admin UI is read-only
- Priority is visibility of leads/customers, not editing
- Search is limited to name, email, and phone
- Default order is by `last_seen_at DESC`

Why:
- gives immediate operational value
- keeps the first UI small and stable
- avoids premature CRM complexity

## Customer edit/delete scope

- Customer admin actions are limited to direct record maintenance
- Editing updates only the customer record itself
- Deleting removes only the customer record from `toptour_customers`
- Requests/inquiries are not altered when a customer is edited or deleted

Why:
- gives operators direct control over customer data quality
- keeps scope small and predictable
- avoids premature relationship and merge complexity

## Preserve customers list context

- Customer admin actions should return the operator to the same list context
- Preserved context is limited to current pagination and search term
- Only explicit, sanitized context parameters are carried across requests

Why:
- improves usability of the admin customers overview
- reduces friction when managing larger customer lists
- keeps the solution small and predictable

## First Requests search/filter scope

- Requests admin overview gets basic search and status filtering first
- Only existing request statuses are used
- Scope is limited to operational usability improvements
- Advanced filtering, export, and bulk actions are deferred

Why:
- Requests are daily operational data
- search and filtering provide immediate value
- small scope reduces risk and keeps development predictable
