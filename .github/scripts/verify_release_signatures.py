"""Download every file in avalonia-update.xml and check it the way the desktop app does
before installing an update: the Ed25519 signature must match the file's exact bytes.

Usage: verify_release_signatures.py <appcast.xml> [--wait-minutes N]

With --wait-minutes, files that are missing or fail are rechecked every minute until the
deadline, because the XML usually deploys before the FileZilla upload has finished.
"""

import argparse
import base64
import os
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET

from cryptography.exceptions import InvalidSignature
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PublicKey

# Release-build key from NetSparkleUpdateService.UpdatePublicKey in the Avalonia repo.
PUBLIC_KEY = "Of6Zmn6HdF02vdJCJB6nutS4ceAPmIpC7fOAXZrb4no="

SPARKLE_NS = "http://www.andymatuschak.org/xml-namespaces/sparkle"

EXPECTED_FILENAMES = {
    "windows": "Argo Books Installer V.{version}.exe",
    "linux": "ArgoBooks-{version}-linux-x64.AppImage",
    "macos-arm64": "ArgoBooks-{version}-osx-arm64.zip",
    "macos-x64": "ArgoBooks-{version}-osx-x64.zip",
}

USER_AGENT = "ArgoBooks-release-verifier (GitHub Actions)"


def sparkle(name):
    return f"{{{SPARKLE_NS}}}{name}"


def load_enclosures(path):
    enclosures = []
    for item in ET.parse(path).getroot().iter("item"):
        enc = item.find("enclosure")
        if enc is None:
            raise SystemExit(f"An <item> in {path} has no <enclosure>")
        enclosures.append({
            "os": enc.get(sparkle("os"), ""),
            "version": enc.get(sparkle("version"), ""),
            "url": enc.get("url", ""),
            "length": enc.get("length", "0"),
            "signature": enc.get(sparkle("edSignature"), ""),
        })
    return enclosures


def check_feed_entry(enc):
    """Problems visible from the XML alone. These never fix themselves, so no retrying."""
    problems = []
    pattern = EXPECTED_FILENAMES.get(enc["os"])
    if pattern is None:
        problems.append(f"unknown sparkle:os '{enc['os']}'")
    else:
        filename = urllib.parse.unquote(enc["url"].rsplit("/", 1)[-1])
        expected = pattern.format(version=enc["version"])
        if filename != expected:
            problems.append(f"URL points at '{filename}', expected '{expected}'")
        if f"/downloads/{enc['version']}/" not in enc["url"]:
            problems.append(f"URL folder does not match version {enc['version']}")
    if not enc["signature"]:
        problems.append("missing sparkle:edSignature")
    return problems


def head(url):
    req = urllib.request.Request(url, method="HEAD", headers={"User-Agent": USER_AGENT})
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            return resp.headers.get("Content-Length"), resp.headers.get("Last-Modified")
    except urllib.error.HTTPError as e:
        if e.code == 404:
            return None
        raise


def download(url, dest):
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(req, timeout=120) as resp, open(dest, "wb") as out:
        while chunk := resp.read(1024 * 1024):
            out.write(chunk)


def verify_file(enc, key, workdir):
    """Returns (ok, message, fingerprint). The fingerprint lets a retry skip re-downloading
    a file that has not changed on the server since it last failed."""
    try:
        info = head(enc["url"])
    except Exception as e:
        return False, f"request failed: {e}", None
    if info is None:
        return False, "not on the server yet (404)", None

    path = os.path.join(workdir, enc["os"])
    try:
        download(enc["url"], path)
    except Exception as e:
        return False, f"download failed: {e}", None

    size = os.path.getsize(path)
    with open(path, "rb") as f:
        data = f.read()
    os.remove(path)

    if enc["length"] not in ("", "0") and int(enc["length"]) != size:
        print(f"::warning::{enc['os']}: length attribute is {enc['length']} but the file is {size} bytes")

    try:
        key.verify(base64.b64decode(enc["signature"]), data)
    except (InvalidSignature, ValueError):
        return False, f"signature does NOT match the file ({size} bytes)", info
    return True, f"signature OK ({size} bytes)", info


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("appcast")
    parser.add_argument("--wait-minutes", type=float, default=0)
    args = parser.parse_args()

    key = Ed25519PublicKey.from_public_bytes(base64.b64decode(PUBLIC_KEY))
    enclosures = load_enclosures(args.appcast)

    feed_problems = []
    for enc in enclosures:
        feed_problems += [f"{enc['os'] or '?'}: {p}" for p in check_feed_entry(enc)]
    seen = [e["os"] for e in enclosures]
    for os_name in EXPECTED_FILENAMES:
        if seen.count(os_name) != 1:
            feed_problems.append(f"{os_name}: expected exactly one enclosure, found {seen.count(os_name)}")
    if feed_problems:
        for p in feed_problems:
            print(f"::error::{p}")
        return 1

    deadline = time.monotonic() + args.wait_minutes * 60
    pending = {enc["os"]: enc for enc in enclosures}
    results = {}
    last_fingerprint = {}

    with tempfile.TemporaryDirectory() as workdir:
        while True:
            for os_name, enc in list(pending.items()):
                if last_fingerprint.get(os_name) is not None:
                    try:
                        current = head(enc["url"])
                    except Exception:
                        current = None
                    if current == last_fingerprint[os_name]:
                        continue
                ok, message, fingerprint = verify_file(enc, key, workdir)
                results[os_name] = (ok, message)
                print(f"{os_name}: {message}", flush=True)
                if ok:
                    del pending[os_name]
                else:
                    last_fingerprint[os_name] = fingerprint

            if not pending or time.monotonic() >= deadline:
                break
            print(f"Waiting a minute, still failing: {', '.join(pending)}", flush=True)
            time.sleep(60)

    print()
    for enc in enclosures:
        ok, message = results[enc["os"]]
        line = f"{enc['os']}: {message} ({enc['url']})"
        print(line if ok else f"::error::{line}")
    return 1 if pending else 0


if __name__ == "__main__":
    sys.exit(main())
