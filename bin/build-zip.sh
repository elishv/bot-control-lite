#!/usr/bin/env bash
# Build the WordPress.org package: build/<slug>/ and build/<slug>.zip, honouring .distignore.
# Usage: bin/build-zip.sh [slug]   (default: bot-control)
set -euo pipefail
cd "$(dirname "$0")/.."
SLUG="${1:-bot-control}"
rm -rf "build/$SLUG" "build/$SLUG.zip"
mkdir -p "build/$SLUG"
EXCLUDES=()
while IFS= read -r line || [ -n "$line" ]; do
	[[ -z "$line" || "$line" == \#* ]] && continue
	EXCLUDES+=("--exclude=./$line" "--exclude=$line")
done < .distignore
tar "${EXCLUDES[@]}" --exclude=./build -cf - . | tar -xf - -C "build/$SLUG"
# Guard: the WordPress.org zip must never contain translation files or an mu-plugin writer.
if find "build/$SLUG" \( -name '*.po' -o -name '*.mo' -o -name '*.l10n.php' \) | grep -q .; then
	echo "Translation files found in build/$SLUG" >&2; exit 1
fi
if grep -rqE 'WPMU_PLUGIN_DIR[^;]*(file_put_contents|fopen|copy)|file_put_contents\([^)]*mu-plugins' "build/$SLUG"; then
	echo "Code writing to mu-plugins found in build/$SLUG" >&2; exit 1
fi
if command -v zip >/dev/null 2>&1; then
	(cd build && zip -qr "$SLUG.zip" "$SLUG")
else
	(cd build && python3 -c 'import shutil,sys; shutil.make_archive(sys.argv[1], "zip", ".", sys.argv[1])' "$SLUG")
fi
echo "build/$SLUG.zip: $(find "build/$SLUG" -type f | wc -l) files"
