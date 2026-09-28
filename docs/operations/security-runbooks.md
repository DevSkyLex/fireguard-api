# Security runbooks

Execute security recovery deliberately in the selected environment and retain sanitized evidence. The security contract remains authoritative.

**Authoritative references:** [Security](../../SECURITY.md) · [Auth](../../src/Auth/MODULE.md) · [OAuth](../../src/OAuth/MODULE.md) · [Session](../../src/Session/MODULE.md).

## Session or token compromise

Determine the affected identity, client/tenant, session and token lifecycle. Use the
owner's revocation operation; interactive sessions and OAuth token-table rows have
different lookup/invalidity behavior. Verify that subsequent protected requests are
denied and keep unaffected scopes isolated. Never copy a compromised token into logs.

## Key rotation

Inventory issuers, verifiers, cookies, workers and stored encrypted payloads before
rotation. Provision keys privately, coordinate the compatible rollout and verify
signature denial plus the required active-session behavior. Encryption-key rotation
needs a data compatibility/re-encryption plan; changing an environment value alone
does not rewrite stored ciphertext.

## Rate limits and account recovery

Use the configured limiter and the affected authentication flow. Keep user/IP
dimensions and tenant/organization checks intact. Changes need success and denial
tests, operational monitoring and a reviewed rollback. A local test override is not
a production mitigation.

## Evidence and escalation

Preserve request/event identifiers, timestamps, source revision and safe outcomes.
Keep key material, passwords, raw tokens and private payloads out of reports. Follow
SECURITY.md for reporting and audit retention; the installation policy owns contacts.

## RBAC Permission Sync

When permissions are updated in code (fixtures/catalog), synchronize them to the database:

```bash
php -d memory_limit=1G bin/console app:authz:sync-permissions --update-roles
```

Use `--dry-run` to preview changes without writing to the database.

## JWT Key Generation

Generate a key pair only when provisioning a new installation or carrying out
a coordinated rotation. Preserve existing keys during ordinary deployment. The
encrypted-key passphrase must match the selected issuer/verifier configuration.

**Generate RSA key pair**:

```bash
# Create directory
mkdir -p config/jwt

# Generate private key (encrypted)
openssl genrsa -out config/jwt/private.key -aes256 4096

# Extract public key
openssl rsa -in config/jwt/private.key -pubout -out config/jwt/public.key

# Set permissions
chmod 600 config/jwt/private.key
chmod 644 config/jwt/public.key
```

**For automated deployments** (no passphrase):

```bash
openssl genrsa -out config/jwt/private.key 4096
openssl rsa -in config/jwt/private.key -pubout -out config/jwt/public.key
```

## Interactive session security rollout

Deploy mandatory session persistence and atomic refresh together. A login, MFA
completion or registration auto-login only returns tokens after its `auth` session
is stored. An existing session recorded with its current token pair continues to
work; a legacy token with no session record requires signing in again. TOTP
enrollments and authenticators are unaffected. Refresh invalidates the previous
access token, so clients must share a single refresh in flight. Monitor refresh
failures and login storage errors without logging token values. Do not restore
the previous permissive authenticator during rollback: it accepted untracked
tokens, including an MFA pre-authentication token on protected API endpoints.
