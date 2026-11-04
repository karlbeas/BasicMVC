#!/usr/bin/env bash
# Builds a self-contained release archive for shared hosting (vendor/ included,
# no dev dependencies). Usage: bash bin/build-release.sh [version]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-$(git -C "$ROOT" describe --tags --always 2>/dev/null || date +%Y%m%d%H%M)}"
BUILD="$ROOT/build"
STAGE="$BUILD/f2s-pro"

rm -rf "$STAGE"
mkdir -p "$STAGE"

# Only committed files; .gitattributes export-ignore drops tests, docs, CI config.
git -C "$ROOT" archive --format=tar HEAD | tar -x -C "$STAGE"

composer install --working-dir="$STAGE" --no-dev --optimize-autoloader --classmap-authoritative \
    --no-interaction --no-progress --prefer-dist

# Dependencies' own tests/docs are useless on the server (present when installed from source).
find "$STAGE/vendor" -mindepth 3 -maxdepth 3 -type d \( -name tests -o -name Tests -o -name docs \) -prune -exec rm -rf {} +

mkdir -p "$STAGE/storage/logs" "$STAGE/storage/cache" "$STAGE/storage/sessions" "$STAGE/storage/uploads"
echo "$VERSION" > "$STAGE/VERSION"

(cd "$BUILD" && rm -f "f2s-pro-$VERSION.zip" && zip -qr "f2s-pro-$VERSION.zip" f2s-pro)
echo "Release: $BUILD/f2s-pro-$VERSION.zip"
