#!/usr/bin/env python3
"""Verify a one-use web approval against the server-held environment key."""

import hashlib
import hmac
import json
import os
import sys
import time


def valid_approval(key, approval, environment, digest, now):
    expires = approval["expires"]
    actor = approval["actor"]
    signature = approval["signature"]
    if not isinstance(expires, int) or not isinstance(actor, int) or actor <= 0 or not isinstance(signature, str):
        return False
    if not now <= expires <= now + 900:
        return False
    message = f"{environment}:{digest}:{expires}:{actor}".encode("ascii")
    expected = hmac.new(key, message, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, signature)


def main():
    if len(sys.argv) != 4 or os.geteuid() != 0:
        return 1
    environment, digest, approval_path = sys.argv[1:]
    if environment not in ("homologacao", "producao"):
        return 1
    if len(digest) != 64 or any(c not in "0123456789abcdef" for c in digest):
        return 1
    try:
        with open(f"/etc/mixhome-ci/{environment}.approval-key", "rb") as key_file:
            key = key_file.read().strip()
        if len(key) < 32 or os.path.islink(approval_path) or os.stat(approval_path).st_size > 512:
            return 1
        with open(approval_path, encoding="ascii") as approval_file:
            approval = json.load(approval_file)
        if valid_approval(key, approval, environment, digest, int(time.time())):
            print(approval["actor"])
            return 0
        return 1
    except (OSError, ValueError, KeyError, TypeError, UnicodeError):
        return 1


if __name__ == "__main__":
    sys.exit(main())
