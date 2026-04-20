## Release script safety

- Release must run only from a clean git workspace
- Release script must never auto-commit all pending changes
- Release ZIP contains the full plugin, not only changed files
- Local backup/archive artifacts must be excluded from packaging

Why:
- prevents accidental commits of unrelated work
- keeps releases reproducible and safe for deployment
