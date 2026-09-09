# Déploiement VPS

Fireguard API utilise le même VPS pour la production et le développement, avec des répertoires, projets Compose, volumes, bases, clés et URL distincts.

| Environnement GitHub | Branche   | API                                     | Mercure                                     | Mailpit                                  | Répertoire VPS                         | Projet Docker               | Préfixe de volumes   |
| -------------------- | --------- | --------------------------------------- | ------------------------------------------- | ---------------------------------------- | -------------------------------------- | --------------------------- | -------------------- |
| `production`         | `main`    | `api.fireguard.valentin-fortin.pro`     | `mercure.fireguard.valentin-fortin.pro`     | —                                        | `/srv/apps/fireguard/production/back`  | `fireguard-production-back` | `back`               |
| `development`        | `develop` | `dev.api.fireguard.valentin-fortin.pro` | `dev.mercure.fireguard.valentin-fortin.pro` | `dev.mail.fireguard.valentin-fortin.pro` | `/srv/apps/fireguard/development/back` | `fireguard-dev-back`        | `fireguard-dev-back` |

Le préfixe `back` conserve les volumes de production créés avant l’introduction du nom explicite du projet Compose. Il ne doit pas être modifié sans migration de volumes. Lors du premier déploiement de cette version, Ansible télécharge d’abord les images, arrête l’ancien projet Compose `back` sans supprimer ses volumes, puis démarre `fireguard-production-back` sur ces mêmes volumes.

## Pipeline

`.github/workflows/deploy-vps.yml` s’exécute sur `main` et `develop` :

1. exécution de la CI réutilisable ;
2. publication d’une image immuable `sha-*` ;
3. mise à jour du canal `latest` pour `main` ou `develop` pour `develop` ;
4. sélection de l’environnement GitHub depuis la branche ;
5. exécution du playbook Ansible dans le répertoire propre à l’environnement.

Ansible vérifie au moins 2,5 Gio de mémoire disponible et 10 Gio de disque libre avant de modifier la stack. Il conserve les sauvegardes, migrations additives des bases `auth` et `main`, synchronisation RBAC, contrôles JWT, contrôle 401 des routes protégées et santé publique.

## Configuration GitHub

`production` accepte uniquement `main`. `development` accepte uniquement `develop`. Les secrets de connexion sont enregistrés dans chaque environnement, même lorsqu’ils ont temporairement la même valeur.

Secrets de déploiement :

- `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`
- `GHCR_TOKEN` si le jeton du workflow ne suffit pas

Secrets applicatifs propres à chaque environnement :

- `APP_SECRET`
- `POSTGRES_AUTH_PASSWORD`, `POSTGRES_MAIN_PASSWORD`
- `MERCURE_JWT_SECRET`
- `OAUTH_ENCRYPTION_KEY`, `WEBHOOK_ENCRYPTION_KEY`
- `BASIC_AUTH_USERS`, `BASIC_AUTH_CREDENTIALS` dans `development`
- `SECURITY_LOG_PII_SALT`
- `GOOGLE_OIDC_CLIENT_SECRET`, `MICROSOFT_OIDC_CLIENT_SECRET` lorsque les fournisseurs sont activés
- `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`

Les variables `API_HOST`, `MERCURE_HOST`, `MAILPIT_HOST`, `DEFAULT_URI`, `MERCURE_PUBLIC_URL`, `TRAEFIK_API_ROUTER_NAME`, `TRAEFIK_MERCURE_ROUTER_NAME`, `TRAEFIK_MAILPIT_ROUTER_NAME`, `DOCKER_PROJECT_NAME` et `VOLUME_PREFIX` pilotent Compose et Traefik. `VPS_APP_DIR` et `VPS_HEALTHCHECK_URL` pilotent Ansible.

Le mode géré `FIREGUARD_MANAGED_ENV=true` rend `.env` depuis les variables et secrets GitHub. Le déploiement refuse toute valeur requise absente, vide ou dangereuse avant l’arrêt de l’application et avant les migrations.

## Développement et Mailpit

`compose.dev.yaml` ajoute Mailpit avec un volume persistant. Son serveur SMTP est joignable par l’application à `smtp://mailpit:1025`.

L’interface est disponible sur `https://dev.mail.fireguard.valentin-fortin.pro` depuis tout réseau. Traefik termine TLS, impose Basic Auth et ajoute `X-Robots-Tag: noindex,nofollow,noarchive` ainsi que `Cache-Control: private,no-store`. Le port `8025` n’est pas publié sur l’hôte et le serveur SMTP `1025` reste limité au réseau Docker.

## OAuth, cookies et Stripe

- Les clients Google et Microsoft du dev utilisent uniquement les callbacks `dev.*` et restent désactivés tant que leurs identifiants dédiés ne sont pas enregistrés.
- Stripe utilise une clé, un webhook et des Price IDs en mode test propres au dev.
- Les cookies sont host-only et `Secure`, ce qui empêche leur partage entre `api.*` et `dev.api.*`.
- `CORS_ALLOW_ORIGIN` et `MERCURE_CORS_ORIGINS` n’autorisent que le frontend du même environnement.

## Rollback

Relancer le workflow depuis la branche concernée avec l’ancien commit ou republier son tag `sha-*`. Les chemins, projets et volumes étant distincts, un rollback du dev ne touche ni les conteneurs ni les bases de production.

Les sauvegardes avant migration restent dans `VPS_APP_DIR/backups/<timestamp>/`.
