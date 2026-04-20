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
