# Deploiement VPS

Ce workflow deploie Fireguard API sur un VPS avec Docker Compose, FrankenPHP, PostgreSQL, Redis, Mercure et Traefik. `Deploy VPS` (`.github/workflows/deploy-vps.yml`) se declenche sur push `main` (ignore les changements limites a `**/*.md` et `docs/**`) ou manuellement. Il lance d'abord `ci.yml` en preflight (gate bloquant), puis construit l'image Docker et la pousse sur GHCR, puis execute Ansible pour deployer sur le VPS, sauvegarder les bases, appliquer les migrations, synchroniser les permissions RBAC et verifier `/api/health`.

## Fichiers ajoutes

- `.github/workflows/deploy-vps.yml`: workflow CI, build, push GHCR, puis execution Ansible.
- `ansible/deploy.yml`: playbook de deploiement VPS.
- `ansible/inventory/production.ini.example`: exemple d'inventaire manuel.
- `compose.prod.yaml`: stack Docker Compose de production pour le VPS.
- `.env.example`: base pour construire le `.env` de production conserve sur le VPS.

## Prerequis VPS

Installer Docker Engine, le plugin Docker Compose, `python3`, `curl` et `bash`/`sh`. L'utilisateur SSH utilise par GitHub Actions doit pouvoir executer `docker`.

Exemple de preparation:

```bash
sudo mkdir -p /opt/fireguard-sso-api
sudo chown "$USER:$USER" /opt/fireguard-sso-api
docker compose version
python3 --version
docker network inspect traefik_proxy >/dev/null 2>&1 || docker network create traefik_proxy
```

Le compose n'expose pas de port public pour l'API. Traefik doit etre connecte au reseau Docker externe `traefik_proxy` et publie `https://api.fireguard.valentin-fortin.pro` pour Symfony et `https://mercure.fireguard.valentin-fortin.pro` pour Mercure.

## Configuration GitHub

Configurer ces secrets dans `Settings > Secrets and variables > Actions > Secrets`:

| Secret | Exemple | Description |
| --- | --- | --- |
| `VPS_HOST` | `203.0.113.10` | IP ou DNS du VPS |
| `VPS_USER` | `deploy` | Utilisateur SSH |
| `VPS_SSH_KEY` | cle privee OpenSSH | Cle autorisee dans `~/.ssh/authorized_keys` sur le VPS |
| `GHCR_TOKEN` | PAT `read:packages` | Optionnel si le `GITHUB_TOKEN` du repo suffit |

Configurer ces variables dans `Settings > Secrets and variables > Actions > Variables`:

| Variable | Exemple | Description |
| --- | --- | --- |
| `VPS_PORT` | `22` | Port SSH, optionnel |
| `VPS_APP_DIR` | `/opt/fireguard-sso-api` | Dossier de deploiement, optionnel |
| `VPS_HEALTHCHECK_URL` | `https://api.fireguard.valentin-fortin.pro/api/health` | Recommande pour verifier Traefik; sinon le workflow teste l'API depuis le conteneur |
| `VPS_HEALTHCHECK_VALIDATE_CERTS` | `true` | Validation TLS du healthcheck public |
| `GHCR_USERNAME` | ton user GitHub | Optionnel |

Le fichier `.env` de production reste sur le VPS dans `VPS_APP_DIR`. Il doit etre shell-compatible. Garde les URLs de base de donnees entre quotes. Ne mets pas `FIREGUARD_IMAGE` a la main: Ansible ajoute ou remplace cette ligne avec l'image GHCR construite par GitHub Actions.

Generer les secrets applicatifs:

```bash
openssl rand -hex 32
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
openssl rand -hex 64
```

Utilise de preference des secrets hexadecimaux. Dans ce workflow, le `.env` de production est charge par `/bin/sh` via Ansible, donc le shell peut interpreter `$VAR`, `$(...)` et les backticks. Si une valeur contient ces caracteres ou syntaxes, regenere une valeur sans metacaracteres shell ou entoure-la avec des quotes simples.

Utilise des mots de passe PostgreSQL URL-encodes dans `AUTH_DATABASE_URL` et `MAIN_DATABASE_URL` si les mots de passe contiennent `@`, `/`, `#`, `?`, `&` ou `%`.

## Premier deploiement

1. Creer `/opt/fireguard-sso-api/.env` sur le VPS a partir de `.env.example`, remplacer tous les `change_me_*`, verifier les domaines publics, `REDIS_URL=redis://redis:6379` et les origines CORS. Ne pas ajouter de valeur factice pour `FIREGUARD_IMAGE`.
2. Proteger le fichier: `chmod 600 /opt/fireguard-sso-api/.env`.
3. Verifier que la branche de production est `main`.
4. Lancer `Deploy VPS` manuellement depuis GitHub Actions ou pousser sur `main`.

Le workflow genere automatiquement `private.key` et `public.key` dans le volume Docker `jwt_keys` au premier deploiement. Ne supprime pas ce volume, sinon tous les tokens signes avec l'ancienne cle deviennent invalides.

## Ansible

Le workflow installe `ansible-core` sur le runner GitHub et genere un inventaire temporaire avec les secrets `VPS_HOST`, `VPS_PORT`, `VPS_USER` et `VPS_SSH_KEY`.

Execution locale possible depuis une machine qui a Ansible:

```bash
ansible-playbook -i ansible/inventory/production.ini ansible/deploy.yml
```

Dans ce cas, exporte au minimum l'image a deployer:

```bash
export IMAGE_REF=ghcr.io/owner/fireguard-sso-api:sha-xxxxxxx
export VPS_APP_DIR=/opt/fireguard-sso-api
export VPS_HEALTHCHECK_URL=https://api.fireguard.valentin-fortin.pro/api/health
```

## Traefik

Le service API est route par Traefik avec ces labels:

```yaml
traefik.http.routers.fireguard-api.rule: Host(`api.fireguard.valentin-fortin.pro`)
traefik.http.services.fireguard-api.loadbalancer.server.port: 8000
```

Mercure est route sur le sous-domaine dedie:

```yaml
traefik.http.routers.fireguard-mercure.rule: Host(`mercure.fireguard.valentin-fortin.pro`)
traefik.http.services.fireguard-mercure.loadbalancer.server.port: 80
```

Dans le `.env` du VPS, expose Mercure et Redis avec:

```env
CORS_ALLOW_ORIGIN='^https://app\.fireguard\.valentin-fortin\.pro$'
REDIS_URL=redis://redis:6379
MERCURE_PUBLIC_URL=https://mercure.fireguard.valentin-fortin.pro/.well-known/mercure
MERCURE_CORS_ORIGINS=https://app.fireguard.valentin-fortin.pro
# Optionnel: duree de vie (secondes) des JWT abonnes Mercure. Defaut: 900.
MERCURE_SUBSCRIBER_TOKEN_TTL=900
```

`MERCURE_CORS_ORIGINS` doit lister les origines exactes de l'application. Le client
envoie le JWT abonne dans un en-tete `Authorization`, ce qui rend la requete SSE
"preflighted": si l'origine n'est pas listee, le hub ne renvoie aucun en-tete CORS et
le navigateur bloque la connexion temps reel (le flux reste silencieux, sans erreur
serveur visible).

Le reseau externe doit deja exister sur le VPS:

```bash
docker network create traefik_proxy
```

## Workers Messenger (etat actuel — a verifier avant mise en production)

`compose.prod.yaml` definit un service `assistant_worker` (consomme le transport
`assistant`), mais **le playbook Ansible ne le demarre pas**: l'etape "Start
application service" execute `docker compose -f compose.yaml up -d app`, qui ne
cible que `app`. Aucune relation `depends_on` ne rattrape `assistant_worker`.

Par ailleurs, `compose.prod.yaml` ne definit **aucun** consommateur pour les
transports `async`, `webhook`, `scheduler_maintenance`, `scheduler_intervention`
et `scheduler_approval` (le compose de developpement, `compose.yaml`, a un
service `async_worker` supplementaire pour `async`+`webhook`, mais lui non plus
ne couvre pas les trois transports `scheduler_*`).

Consequence documentee dans `OPERATIONS.md` (section "Scheduled Tasks"): tant
qu'aucun processus ne consomme ces transports en production, la publication
d'intervention, la materialisation des recurrences, le recalcul de
maintenance, les regles d'automatisation, les imports CSV, la livraison des
webhooks, les reponses de l'assistant IA et l'expiration des demandes
d'approbation ne s'executent jamais silencieusement (aucune erreur visible).

Avant une mise en production, demarrer manuellement (ou via supervisor/systemd,
ou en etendant le compose et le playbook) le worker documente dans
`OPERATIONS.md`:

```bash
php bin/console messenger:consume \
  async webhook assistant \
  scheduler_maintenance scheduler_intervention scheduler_approval \
  --time-limit=3600
```

## Commandes utiles sur le VPS

```bash
cd /opt/fireguard-sso-api
docker compose ps
docker compose logs -f app
docker compose exec app php bin/console doctrine:migrations:status --configuration=config/migrations/auth.yaml --env=prod
docker compose exec app php bin/console doctrine:migrations:status --configuration=config/migrations/main.yaml --env=prod
```

Les sauvegardes avant migration sont creees dans `backups/YYYYMMDDHHMMSS/auth.dump` et `backups/YYYYMMDDHHMMSS/main.dump`.

Restauration manuelle:

```bash
cd /opt/fireguard-sso-api
set -a
. ./.env
set +a
docker compose exec -T auth_database pg_restore -U "$POSTGRES_AUTH_USER" -d "$POSTGRES_AUTH_DB" --clean --if-exists < backups/20260522120000/auth.dump
docker compose exec -T main_database pg_restore -U "$POSTGRES_MAIN_USER" -d "$POSTGRES_MAIN_DB" --clean --if-exists < backups/20260522120000/main.dump
```
