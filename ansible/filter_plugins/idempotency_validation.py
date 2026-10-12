"""Secret-free predicates for the effective application idempotency configuration."""

import base64
import binascii
import json
import re


def _decoded_size(encoded):
    """Match strict PHP base64 decoding, including its accepted missing padding."""
    if not isinstance(encoded, str):
        return None
    compact = re.sub(r"[ \t\r\n]", "", encoded)
    if not re.fullmatch(r"[A-Za-z0-9+/]*={0,2}", compact):
        return None
    content = compact.rstrip("=")
    remainder = len(content) % 4
    if remainder == 1:
        return None
    padding = (4 - remainder) % 4
    supplied = len(compact) - len(content)
    if supplied and supplied != padding:
        return None
    try:
        return len(base64.b64decode(content + "=" * padding, validate=True))
    except (binascii.Error, ValueError):
        return None


def _version_valid(version):
    """Require the runtime's bounded version names and string PHP array keys."""
    if not isinstance(version, str) or not re.fullmatch(r"[A-Za-z0-9_-]{1,32}", version):
        return False
    # PHP's associative JSON decoder converts canonical integer object keys to
    # integers; VersionedAesGcmAdapter deliberately rejects those version keys.
    if version != "-0" and re.fullmatch(r"-?(?:0|[1-9][0-9]*)", version):
        if -(2**63) <= int(version) <= 2**63 - 1:
            return False
    return True


def http_idempotency_valid(environment):
    """Return a boolean only, never secret values or value-bearing exceptions."""
    if not isinstance(environment, dict):
        return False
    hmac_size = _decoded_size(environment.get("HTTP_IDEMPOTENCY_HMAC_KEY"))
    if hmac_size is None or hmac_size < 32:
        return False
    encoded_keys = environment.get("HTTP_IDEMPOTENCY_ENCRYPTION_KEYS")
    active = environment.get("HTTP_IDEMPOTENCY_ENCRYPTION_ACTIVE_VERSION")
    if not isinstance(encoded_keys, str) or not _version_valid(active):
        return False
    try:
        keys = json.loads(encoded_keys)
    except (ValueError, TypeError, RecursionError):
        return False
    if not isinstance(keys, dict) or not keys or active not in keys:
        return False
    return all(_version_valid(version) and _decoded_size(key) == 32 for version, key in keys.items())


class FilterModule:
    """Expose only the redacted predicate to Ansible's private configuration task."""

    def filters(self):
        return {"fireguard_http_idempotency_valid": http_idempotency_valid}
