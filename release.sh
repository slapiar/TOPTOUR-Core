#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="toptour-core"
PLUGIN_FILE="toptour-core.php"
BUILD_DIR="build"
DIST_DIR="dist"

if [[ ! -f "$PLUGIN_FILE" ]]; then
  echo "Chyba: súbor $PLUGIN_FILE neexistuje."
  exit 1
fi

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Chyba: nie si v git repozitári."
  exit 1
fi

CURRENT_BRANCH="$(git branch --show-current)"
if [[ "$CURRENT_BRANCH" == "main" ]]; then
  echo "Chyba: release nerob priamo na main."
  exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
  echo "Chyba: workspace nie je čistý. Commitni alebo stashni zmeny pred release."
  exit 1
fi

CURRENT_VERSION="$(grep -E "^[[:space:]]*\*[[:space:]]+Version:" "$PLUGIN_FILE" | sed -E 's/.*Version:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+).*/\1/')"

if [[ -z "${CURRENT_VERSION:-}" ]]; then
  echo "Chyba: nepodarilo sa zistiť aktuálnu verziu z $PLUGIN_FILE."
  exit 1
fi

increment_version() {
  local version="$1"
  local part="${2:-patch}"
  IFS='.' read -r major minor patch <<< "$version"

  case "$part" in
    major)
      major=$((major + 1))
      minor=0
      patch=0
      ;;
    minor)
      minor=$((minor + 1))
      patch=0
      ;;
    patch)
      patch=$((patch + 1))
      ;;
    *)
      echo "Chyba: neznámy typ inkrementácie: $part"
      exit 1
      ;;
  esac

  echo "${major}.${minor}.${patch}"
}

RELEASE_TYPE="${1:-patch}"
NEW_VERSION="$(increment_version "$CURRENT_VERSION" "$RELEASE_TYPE")"

echo "Aktuálna verzia: $CURRENT_VERSION"
echo "Nová verzia:     $NEW_VERSION"

sed -i -E "s/^([[:space:]]*\*[[:space:]]+Version:[[:space:]]*).*/\1$NEW_VERSION/" "$PLUGIN_FILE"
sed -i -E "s/(define\('TOPTOUR_CORE_VERSION',[[:space:]]*')[0-9]+\.[0-9]+\.[0-9]+('.*)/\1$NEW_VERSION\2/" "$PLUGIN_FILE"

git add "$PLUGIN_FILE"
git commit -m "chore(release): bump version to $NEW_VERSION"

mkdir -p "$BUILD_DIR" "$DIST_DIR"
rm -rf "$BUILD_DIR/$PLUGIN_SLUG"
mkdir -p "$BUILD_DIR/$PLUGIN_SLUG"

rsync -av \
  --exclude=".git" \
  --exclude=".github" \
  --exclude=".vscode" \
  --exclude="$BUILD_DIR" \
  --exclude="$DIST_DIR" \
  --exclude="*.zip" \
  --exclude="*.tar.gz" \
  --exclude="*.sha256" \
  --exclude="*.log" \
  --exclude=".DS_Store" \
  --exclude="node_modules" \
  --exclude="release.sh" \
  ./ "$BUILD_DIR/$PLUGIN_SLUG/"

ZIP_FILE="$DIST_DIR/${PLUGIN_SLUG}-${NEW_VERSION}.zip"
rm -f "$ZIP_FILE"

(
  cd "$BUILD_DIR"
  zip -r "../$ZIP_FILE" "$PLUGIN_SLUG" >/dev/null
)

git tag "v$NEW_VERSION"

echo
echo "Hotovo."
echo "ZIP: $ZIP_FILE"
echo "Tag: v$NEW_VERSION"
echo
echo "Ďalšie kroky:"
echo "  git push"
echo "  git push --tags"