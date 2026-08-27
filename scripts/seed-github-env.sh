#!/usr/bin/env bash
#
# Seed the GitHub `production` environment from a copy of the VPS env file.
#
# WHY FROM THE VPS FILE AND NOT BY HAND. Six of these values already exist and
# must not be invented: POSTGRES_AUTH_PASSWORD and POSTGRES_MAIN_PASSWORD are the
# passwords the running Postgres containers were created with, and the deploy
# feeds AUTH_DATABASE_URL to `ALTER USER ... WITH PASSWORD` on the live database.
# A fresh value there is not rejected, it is APPLIED — you would rotate the
# database password to something the app does not know.
#
#   scp <vps>:/srv/apps/fireguard/production/back/.env ./prod.env
#   ./scripts/seed-github-env.sh ./prod.env
#   shred -u ./prod.env          # it holds every production secret
#
# The script pushes what the file has, and REFUSES to finish while a required key
# is absent from it — those are the ones you must add by hand, and the ones the
# deploy preflight is currently failing on.
#
# Nothing here writes to the VPS. Re-running is safe: `gh variable set` and
# `gh secret set` both overwrite.

set -euo pipefail

ENV_FILE="${1:-}"
REPO="${GH_REPO:-DevSkyLex/fireguard-api}"
GH_ENVIRONMENT="${GH_ENVIRONMENT:-production}"

if [ -z "$ENV_FILE" ] || [ ! -r "$ENV_FILE" ]; then
  echo "usage: $0 <path-to-a-copy-of-the-production-.env>" >&2
  exit 64
fi

# The fourteen that must never be readable after the fact. Everything else is
# configuration and goes in as a variable, so it can be reviewed and diffed.
SECRETS="
APP_SECRET
MAILER_DSN
MERCURE_JWT_SECRET
OAUTH_ENCRYPTION_KEY
POSTGRES_AUTH_PASSWORD
POSTGRES_MAIN_PASSWORD
SECURITY_LOG_PII_SALT
SMS_DSN
STRIPE_SECRET_KEY
STRIPE_WEBHOOK_SECRET
WEBHOOK_ENCRYPTION_KEY
"

repo_root="$(cd "$(dirname "$0")/.." && pwd)"

tmp_declared="$(mktemp)"
tmp_pinned="$(mktemp)"
trap 'rm -f "$tmp_declared" "$tmp_pinned"' EXIT

# The required set, recomputed the way ansible/deploy.yml does it: the
# application's declared contract, plus what compose marks mandatory, minus what
# compose pins in its own `environment:` blocks -- those win over `env_file:`, so
# the file never has to carry them. FIREGUARD_IMAGE is written by the deploy
# itself. tests/Architecture/Unit/DeployEnvContractTest.php keeps this resolution
# honest against the workflow.
#
# Deliberately awk and not python: `command -v python3` succeeds under Git Bash
# on Windows and resolves to the Microsoft Store stub, which prints an error and
# exits non-zero. Probing for a working interpreter is more moving parts than the
# parsing is worth.
required_keys() {
  {
    grep -oE '^[A-Z][A-Z0-9_]*=' "$repo_root/.env.example" | tr -d '='
    grep -oE '\$\{[A-Z][A-Z0-9_]*:\?' "$repo_root/compose.prod.yaml"       | sed -e 's/^\${//' -e 's/:?$//'
  } | sort -u > "$tmp_declared"

  awk '
    /^    environment:[[:space:]]*$/ { in_env = 1; next }
    in_env && /^      [A-Z][A-Z0-9_]*:/ {
      key = $1; sub(/:$/, "", key)
      value = $0; sub(/^      [A-Z][A-Z0-9_]*:[[:space:]]*/, "", value)
      if (value != "" && value !~ /^\$\{/) { print key }
      next
    }
    in_env && $0 !~ /^      / && NF { in_env = 0 }
  ' "$repo_root/compose.prod.yaml" > "$tmp_pinned"

  # Computed by the template, never supplied: the image ref comes from the build
  # job, and the two connection URLs are derived from the POSTGRES_* parts.
  printf '%s
' FIREGUARD_IMAGE AUTH_DATABASE_URL MAIN_DATABASE_URL >> "$tmp_pinned"
  sort -u -o "$tmp_pinned" "$tmp_pinned"

  comm -23 "$tmp_declared" "$tmp_pinned"
}

value_of() {
  # Last assignment wins, as dotenv does. Quotes are stripped; the value is
  # otherwise passed through untouched.
  sed -n "s/^$1=//p" "$ENV_FILE" | tail -n 1 | sed -e "s/^'\(.*\)'$/\1/" -e 's/^"\(.*\)"$/\1/'
}

missing=""
pushed_vars=0
pushed_secrets=0

for key in $(required_keys); do
  value="$(value_of "$key")"

  if [ -z "$value" ]; then
    missing="$missing $key"
    continue
  fi

  if echo "$SECRETS" | grep -qx "$key"; then
    # No --body: `gh secret set` reads the value from standard input when the flag
    # is absent. `--body -` would set the literal string "-" on every secret.
    printf '%s' "$value" | gh secret set "$key" --repo "$REPO" --env "$GH_ENVIRONMENT"
    echo "  secret   $key"
    pushed_secrets=$((pushed_secrets + 1))
  else
    gh variable set "$key" --repo "$REPO" --env "$GH_ENVIRONMENT" --body "$value"
    echo "  variable $key = $value"
    pushed_vars=$((pushed_vars + 1))
  fi
done

echo
echo "$pushed_vars variables, $pushed_secrets secrets -> $REPO ($GH_ENVIRONMENT)"

if [ -n "$missing" ]; then
  echo
  echo "STILL MISSING — the VPS file does not have these either, so they need a real value:"
  for key in $missing; do echo "  $key"; done
  echo
  echo "Set each one with:"
  echo "  gh variable set <KEY> --repo $REPO --env $GH_ENVIRONMENT --body '<value>'"
  echo "  gh secret   set <KEY> --repo $REPO --env $GH_ENVIRONMENT"
  echo
  echo "Do NOT turn FIREGUARD_MANAGED_ENV on until this list is empty: the playbook"
  echo "would refuse to render anyway, and refusing is the point."
  exit 1
fi

echo
echo "Every required key is set. To let the deploy render the VPS env file:"
echo "  gh variable set FIREGUARD_MANAGED_ENV --repo $REPO --env $GH_ENVIRONMENT --body true"
