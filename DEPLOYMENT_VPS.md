# Deploiement VPS

Ce workflow deploie Fireguard API sur un VPS avec Docker Compose, PostgreSQL, Redis, Mercure et Traefik. GitHub Actions construit l'image Docker, la pousse sur GHCR, copie la configuration sur le VPS, lance les sauvegardes, applique les migrations, synchronise les permissions RBAC et verifie `/api/health`.

## Fichiers ajoutes

- `.github/workflows/deploy-vps.yml`: workflow CI, build, push GHCR, deploiement SSH.
- `compose.prod.yaml`: stack Docker Compose de production pour le VPS.
- `.env.example`: base pour construire le secret `VPS_ENV_FILE`.

## Prerequis VPS

Installer Docker Engine, le plugin Docker Compose, `curl`, `openssl` et `bash`/`sh`. L'utilisateur SSH utilise par GitHub Actions doit pouvoir executer `docker`.

Exemple de preparation:

```bash
sudo mkdir -p /opt/fireguard-sso-api
sudo chown "$USER:$USER" /opt/fireguard-sso-api
docker compose version
docker network inspect traefik_proxy >/dev/null 2>&1 || docker network create traefik_proxy
```

Le compose n'expose pas de port public pour l'API. Traefik doit etre connecte au reseau Docker externe `traefik_proxy` et publie `https://api.fireguard.valentin-fortin.pro` pour Symfony et `https://mercure.api.fireguard.valentin-fortin.pro` pour Mercure.

## Secrets GitHub

Configurer ces secrets dans `Settings > Secrets and variables > Actions`:

| Secret | Exemple | Description |
| --- | --- | --- |
| `VPS_HOST` | `203.0.113.10` | IP ou DNS du VPS |
| `VPS_PORT` | `22` | Port SSH, optionnel |
| `VPS_USER` | `deploy` | Utilisateur SSH |
| `VPS_SSH_KEY` | cle privee OpenSSH | Cle autorisee dans `~/.ssh/authorized_keys` sur le VPS |
| `VPS_APP_DIR` | `/opt/fireguard-sso-api` | Dossier de deploiement, optionnel |
| `VPS_ENV_FILE` | contenu env de production | Variables de production completes |
| `VPS_HEALTHCHECK_URL` | `https://api.fireguard.valentin-fortin.pro/api/health` | Recommande pour verifier Traefik; sinon le workflow teste l'API depuis le conteneur |
| `GHCR_USERNAME` | ton user GitHub | Optionnel |
| `GHCR_TOKEN` | PAT `read:packages` | Optionnel si le `GITHUB_TOKEN` du repo suffit |

`VPS_ENV_FILE` doit etre un fichier env shell-compatible. Garde les URLs de base de donnees entre quotes.

Generer les secrets applicatifs:

```bash
openssl rand -hex 32
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
openssl rand -hex 64
```

Utilise des mots de passe PostgreSQL URL-encodes dans `AUTH_DATABASE_URL` et `MAIN_DATABASE_URL` si les mots de passe contiennent `@`, `/`, `#`, `?`, `&` ou `%`.

## Premier deploiement

1. Construire le contenu `VPS_ENV_FILE`, remplacer tous les `change_me_*`, verifier les domaines publics et les origines CORS.
2. Coller le contenu complet dans le secret `VPS_ENV_FILE`.
3. Verifier que la branche de production est `main`.
4. Lancer `Deploy VPS` manuellement depuis GitHub Actions ou pousser sur `main`.

Le workflow genere automatiquement `jwt/private.key` et `jwt/public.key` dans `VPS_APP_DIR` au premier deploiement. Ne supprime pas ce dossier, sinon tous les tokens signes avec l'ancienne cle deviennent invalides.

## Traefik

Le service API est route par Traefik avec ces labels:

```yaml
traefik.http.routers.fireguard-api.rule: Host(`api.fireguard.valentin-fortin.pro`)
traefik.http.services.fireguard-api.loadbalancer.server.port: 8000
```

Mercure est route sur le sous-domaine dedie:

```yaml
traefik.http.routers.fireguard-mercure.rule: Host(`mercure.api.fireguard.valentin-fortin.pro`)
traefik.http.services.fireguard-mercure.loadbalancer.server.port: 80
```

Dans `VPS_ENV_FILE`, expose Mercure avec:

```env
MERCURE_PUBLIC_URL=https://mercure.api.fireguard.valentin-fortin.pro/.well-known/mercure
```

Le reseau externe doit deja exister sur le VPS:

```bash
docker network create traefik_proxy
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
