"""Checks each update package and download of a Velopack release on argorobots.com against its size, SHA256 and Ed25519 signature.

Usage: verify_velopack_release.py <updates folder> [--wait-minutes N] [--base-url URL]
"""

import argparse
import base64
import hashlib
import json
import os
import sys
import tempfile
import time
import urllib.error
import urllib.request

from cryptography.exceptions import InvalidSignature
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PublicKey

# Release-build key from VelopackUpdateService.UpdatePublicKey in the Avalonia repo.
PUBLIC_KEY = "Of6Zmn6HdF02vdJCJB6nutS4ceAPmIpC7fOAXZrb4no="

CHANNELS = ["win", "osx-arm64", "osx-x64", "linux"]
WINDOWS_SETUP = "ArgoBooksApp-win-Setup.exe"
USER_AGENT = "ArgoBooks-release-verifier (GitHub Actions)"


def request(url, method="GET"):
    return urllib.request.Request(url, method=method, headers={"User-Agent": USER_AGENT})


def head_size(url):
    try:
        with urllib.request.urlopen(request(url, "HEAD"), timeout=30) as resp:
            return int(resp.headers.get("Content-Length") or -1)
    except urllib.error.HTTPError as e:
        if e.code == 404:
            return None
        raise


def download(url, dest):
    with urllib.request.urlopen(request(url), timeout=300) as resp, open(dest, "wb") as out:
        while chunk := resp.read(1024 * 1024):
            out.write(chunk)


def check_signed_file(url, signature_path, key, workdir, size=None, sha256=None):
    """Returns (ok, message)."""
    if not os.path.exists(signature_path):
        return False, f"no signature committed at {signature_path}"
    try:
        if head_size(url) is None:
            return False, "not on the server yet (404)"
        path = os.path.join(workdir, "download")
        download(url, path)
    except Exception as e:
        return False, f"download failed: {e}"

    with open(path, "rb") as f:
        data = f.read()
    os.remove(path)

    if size is not None and len(data) != size:
        return False, f"is {len(data)} bytes, the release records {size}"
    if sha256 and hashlib.sha256(data).hexdigest().lower() != sha256.lower():
        return False, "SHA256 does not match the update list"
    with open(signature_path, encoding="utf-8") as f:
        signature = f.read().strip()
    try:
        key.verify(base64.b64decode(signature), data)
    except (InvalidSignature, ValueError):
        return False, f"signature does NOT match the file ({len(data)} bytes)"
    return True, f"signature OK ({len(data)} bytes)"


def check_download(url, size):
    """Returns (ok, message) for a file on the download page."""
    actual = head_size(url)
    if actual is None:
        return False, "not on the server yet (404)"
    if actual != size:
        return False, f"is {actual} bytes, release.json says {size}"
    return True, "on the download page"


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("folder")
    parser.add_argument("--wait-minutes", type=float, default=0)
    parser.add_argument("--base-url", default="https://argorobots.com")
    args = parser.parse_args()

    key = Ed25519PublicKey.from_public_bytes(base64.b64decode(PUBLIC_KEY))
    updates_url = f"{args.base_url}/resources/{os.path.basename(os.path.normpath(args.folder))}"

    with open(os.path.join(args.folder, "release.json"), encoding="utf-8") as f:
        release = json.load(f)
    version = release["version"]

    checks = {}
    problems = []
    for channel in CHANNELS:
        feed_path = os.path.join(args.folder, f"releases.{channel}.json")
        if not os.path.exists(feed_path):
            problems.append(f"{channel}: releases.{channel}.json is not committed")
            continue
        with open(feed_path, encoding="utf-8") as f:
            assets = [a for a in json.load(f)["Assets"] if a["Type"] == "Full"]
        latest = max(assets, key=lambda a: [int(p) for p in a["Version"].split(".")], default=None)
        if latest is None:
            problems.append(f"{channel}: the update list has no full package")
            continue
        if latest["Version"] != version:
            problems.append(f"{channel}: the update list offers {latest['Version']}, release.json says {version}")
            continue
        name = latest["FileName"]
        checks[f"{channel} update"] = lambda workdir, n=name, a=latest: check_signed_file(
            f"{updates_url}/{n}", os.path.join(args.folder, n + ".sig"), key, workdir, a["Size"], a.get("SHA256"))

    checks["windows setup"] = lambda workdir: check_signed_file(
        f"{updates_url}/{WINDOWS_SETUP}", os.path.join(args.folder, WINDOWS_SETUP + ".sig"), key, workdir)

    setup_signature = os.path.join(args.folder, WINDOWS_SETUP + ".sig")
    for name, size in release.get("downloads", {}).items():
        url = f"{args.base_url}/resources/downloads/{version}/{urllib.request.quote(name)}"
        if name.endswith("-win-Setup.exe"):
            # The build copies the signed Setup.exe here under a versioned name, so the same signature has to match.
            checks[f"download {name}"] = lambda workdir, u=url, s=size: check_signed_file(u, setup_signature, key, workdir, s)
        else:
            checks[f"download {name}"] = lambda workdir, u=url, s=size: check_download(u, s)
    if len(release.get("downloads", {})) != 4:
        problems.append(f"release.json lists {len(release.get('downloads', {}))} downloads, expected 4")

    if problems:
        for p in problems:
            print(f"::error::{p}")
        return 1

    deadline = time.monotonic() + args.wait_minutes * 60
    pending = dict(checks)
    results = {}
    with tempfile.TemporaryDirectory() as workdir:
        while True:
            for name, check in list(pending.items()):
                try:
                    ok, message = check(workdir)
                except Exception as e:
                    ok, message = False, f"request failed: {e}"
                results[name] = (ok, message)
                print(f"{name}: {message}", flush=True)
                if ok:
                    del pending[name]
            if not pending or time.monotonic() >= deadline:
                break
            print(f"Waiting a minute, still failing: {', '.join(pending)}", flush=True)
            time.sleep(60)

    print()
    for name, (ok, message) in results.items():
        print(f"{name}: {message}" if ok else f"::error::{name}: {message}")
    return 1 if pending else 0


if __name__ == "__main__":
    sys.exit(main())
