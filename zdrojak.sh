#!/usr/bin/env bash
set -euo pipefail

BACKUP_SLUG="toptour-core-source-backup"
BACKUP_DIR="backup"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
ARCHIVE_NAME="${BACKUP_SLUG}-${TIMESTAMP}.tar.gz"
ARCHIVE_PATH="${BACKUP_DIR}/${ARCHIVE_NAME}"
CHECKSUM_PATH="${ARCHIVE_PATH}.sha256"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Chyba: nie si v git repozitári."
  exit 1
fi

mkdir -p "$BACKUP_DIR"

# Full source backup for disaster recovery.
tar -czf "$ARCHIVE_PATH" \
  --exclude="./${BACKUP_DIR}" \
  .

sha256sum "$ARCHIVE_PATH" > "$CHECKSUM_PATH"

echo
echo "Hotovo."
echo "Backup: $ARCHIVE_PATH"
echo "Checksum: $CHECKSUM_PATH"
echo