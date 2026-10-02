"""Deployment filters for explicit, reviewed client-address proxy lists."""

import csv
import ipaddress


def trusted_proxies_valid(value):
    """Allow a blank list or CSV IP/CIDR entries, never framework aliases."""
    if not isinstance(value, str):
        return False
    if not value.strip():
        return True
    # Symfony's csv env processor does not trim unquoted fields. Validate the
    # original representation instead of accepting a normalized proxy list that
    # differs from the application runtime; globally blank still trusts nobody.
    if any(character.isspace() for character in value):
        return False
    try:
        entries = next(csv.reader([value], strict=True))
        for entry in entries:
            if not entry:
                return False
            if "%" in entry:
                return False
            # ipaddress also rejects REMOTE_ADDR, private_ranges, PRIVATE_SUBNETS,
            # DNS names and quoted aliases without echoing them into an error.
            if "/" in entry:
                prefix = entry.rsplit("/", 1)[1]
                if not prefix.isascii() or not prefix.isdigit():
                    return False
                ipaddress.ip_network(entry, strict=False)
            else:
                ipaddress.ip_address(entry)
        return bool(entries)
    except (csv.Error, ValueError):
        return False


class FilterModule:
    """Expose the predicate to playbooks without logging configuration values."""

    def filters(self):
        return {"fireguard_trusted_proxies_valid": trusted_proxies_valid}
