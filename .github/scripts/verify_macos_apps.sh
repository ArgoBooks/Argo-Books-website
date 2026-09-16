#!/bin/bash
# Downloads both macOS zips listed in avalonia-update.xml and checks each app bundle the way
# Gatekeeper will: Developer ID signature, hardened runtime, notarization ticket stapled.
# Also checks the processor architecture and bundle version, so a swapped or stale zip fails.
#
# Usage: verify_macos_apps.sh avalonia-update.xml

set -uo pipefail

APPCAST="$1"
WORK=$(mktemp -d)
FAILED=0

fail() {
    echo "::error::$1"
    FAILED=1
}

ENTRIES=$(python3 - "$APPCAST" <<'PY'
import sys, xml.etree.ElementTree as ET
ns = "{http://www.andymatuschak.org/xml-namespaces/sparkle}"
for enc in ET.parse(sys.argv[1]).getroot().iter("enclosure"):
    os_name = enc.get(ns + "os", "")
    if os_name.startswith("macos"):
        print(os_name, enc.get(ns + "version"), enc.get("url"))
PY
)

if [ "$(echo "$ENTRIES" | grep -c macos)" -ne 2 ]; then
    echo "::error::Expected two macOS enclosures in $APPCAST"
    exit 1
fi

while read -r OS_NAME VERSION URL; do
    echo "=== $OS_NAME $VERSION"
    case "$OS_NAME" in
        macos-arm64) EXPECTED_ARCH="arm64" ;;
        macos-x64) EXPECTED_ARCH="x86_64" ;;
        *) fail "$OS_NAME: unknown macOS enclosure"; continue ;;
    esac

    DIR="$WORK/$OS_NAME"
    mkdir -p "$DIR"
    if ! curl -fsSL -A "ArgoBooks-release-verifier (GitHub Actions)" -o "$DIR/app.zip" "$URL"; then
        fail "$OS_NAME: download failed ($URL)"
        continue
    fi
    ditto -x -k "$DIR/app.zip" "$DIR"
    APP="$DIR/Argo Books.app"
    if [ ! -d "$APP" ]; then
        fail "$OS_NAME: zip does not contain 'Argo Books.app'"
        continue
    fi

    if ! codesign --verify --deep --strict --verbose=2 "$APP"; then
        fail "$OS_NAME: codesign verification failed"
    fi

    DETAILS=$(codesign -dvv "$APP" 2>&1)
    echo "$DETAILS" | grep -E "^(Authority|TeamIdentifier|Timestamp)="
    echo "$DETAILS" | grep -q "^Authority=Developer ID Application:" \
        || fail "$OS_NAME: not signed with a Developer ID Application certificate"
    echo "$DETAILS" | grep -q "^Timestamp=" \
        || fail "$OS_NAME: signature has no secure timestamp"
    echo "$DETAILS" | grep -qE "flags=.*runtime" \
        || fail "$OS_NAME: hardened runtime is not enabled, which notarization requires"

    SPCTL=$(spctl --assess --type execute --verbose=4 "$APP" 2>&1)
    echo "$SPCTL"
    echo "$SPCTL" | grep -q "accepted" && echo "$SPCTL" | grep -q "Notarized Developer ID" \
        || fail "$OS_NAME: Gatekeeper does not accept it as a notarized Developer ID app"

    xcrun stapler validate "$APP" \
        || fail "$OS_NAME: notarization ticket is not stapled, so first launch offline is blocked"

    ARCHS=$(lipo -archs "$APP/Contents/MacOS/Argo Books")
    echo "Architecture: $ARCHS"
    [ "$ARCHS" = "$EXPECTED_ARCH" ] \
        || fail "$OS_NAME: executable is '$ARCHS', expected '$EXPECTED_ARCH'"

    BUNDLE_VERSION=$(/usr/libexec/PlistBuddy -c "Print :CFBundleShortVersionString" "$APP/Contents/Info.plist")
    echo "Bundle version: $BUNDLE_VERSION"
    [ "$BUNDLE_VERSION" = "$VERSION" ] \
        || fail "$OS_NAME: bundle version is $BUNDLE_VERSION, expected $VERSION"

    rm -rf "$DIR"
done <<< "$ENTRIES"

rm -rf "$WORK"
exit $FAILED
