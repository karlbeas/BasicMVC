#!/usr/bin/env bash
# Builds self-contained release archives for shared hosting (vendor/ included,
# no dev dependencies). Usage: bash bin/build-release.sh [version]
#   build/f2s-pro-<v>-public_html.zip  contents at the zip root: unzip straight into public_html
#   build/f2s-pro-<v>.zip              f2s-pro/ folder: app outside the web root (docroot = public/)
# Both contain a one-time installation code (storage/install.key) for the web installer.
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
find "$STAGE/vendor" -mindepth 3 -maxdepth 3 \( -name tests -o -name Tests -o -name docs -o -name examples -o -name '.*' \) -prune -exec rm -rf {} +
find "$STAGE/vendor" -mindepth 3 -maxdepth 3 -type f \( -name '*.dist' -o -name '*.xml' -o -name '*.yml' -o -name '*.neon' \) -delete

printf '# Internal directory: never served over HTTP.\nRequire all denied\n' > "$STAGE/vendor/.htaccess"
mkdir -p "$STAGE/storage/logs" "$STAGE/storage/cache" "$STAGE/storage/sessions" "$STAGE/storage/uploads"
echo "$VERSION" > "$STAGE/VERSION"

# One-time code required by the web installer (/install), deleted after installation.
KEY="$(php -r 'echo strtoupper(bin2hex(random_bytes(10)));')"
echo "$KEY" > "$STAGE/storage/install.key"
echo "$KEY" > "$BUILD/INSTALL-KEY-$VERSION.txt"

(cd "$BUILD" && rm -f "f2s-pro-$VERSION.zip" && zip -qr "f2s-pro-$VERSION.zip" f2s-pro)
(cd "$STAGE" && rm -f "$BUILD/f2s-pro-$VERSION-public_html.zip" && zip -qr "$BUILD/f2s-pro-$VERSION-public_html.zip" .)
echo "Release (public_html): $BUILD/f2s-pro-$VERSION-public_html.zip"
echo "Release (app outside web root): $BUILD/f2s-pro-$VERSION.zip"
echo "Installation code: $KEY"
