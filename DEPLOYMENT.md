# Déploiement VPS

Fireguard API utilise le même VPS pour la production et le développement, avec des répertoires, projets Compose, volumes, bases, clés et URL distincts.

| Environnement GitHub | Branche   | API                                     | Mercure                                     | Mailpit                                  | Répertoire VPS                         | Projet Docker               | Préfixe de volumes   |
| -------------------- | --------- | --------------------------------------- | ------------------------------------------- | ---------------------------------------- | -------------------------------------- | --------------------------- | -------------------- |
| `production`         | `main`    | `api.fireguard.valentin-fortin.pro`     | `mercure.fireguard.valentin-fortin.pro`     | —                                        | `/srv/apps/fireguard/production/back`  | `fireguard-production-back` | `back`               |
| `development`        | `develop` | `dev.api.fireguard.valentin-fortin.pro` | `dev.mercure.fireguard.valentin-fortin.pro` | `dev.mail.fireguard.valentin-fortin.pro` | `/srv/apps/fireguard/development/back` | `fireguard-dev-back`        | `fireguard-dev-back` |

Le préfixe `back` conserve les volumes de production créés avant l’introduction du nom explicite du projet Compose. Il ne doit pas être modifié sans migration de volumes. Lors du premier déploiement de cette version, Ansible télécharge d’abord les images, arrête l’ancien projet Compose `back` sans supprimer ses volumes, puis démarre `fireguard-production-back` sur ces mêmes volumes.

## Pipeline

`.github/workflows/deploy-vps.yml` réagit à la fin de la CI déclenchée par
un push sur `main` ou `develop`, ainsi qu’à un lancement manuel sur ces branches :

1. vérification de l’exécution CI, de sa branche, de son SHA et du quality gate
   SonarQube correspondant ; refus si la baseline n’a pas été activée ;
2. exclusion des pushes contenant uniquement des fichiers Markdown ou `docs/**` ;
3. lancement d’une seconde exécution du workflow sur la branche validée :
   GitHub applique ainsi la restriction `main`/`develop` de l’environnement ;
   cette exécution revérifie le même identifiant CI et refuse une branche avancée ;
4. construction du commit validé et publication d’une image étiquetée
   `sha-<SHA complet>` avec la provenance OCI du dépôt et du commit ;
5. déploiement du digest immuable de l’image, dans l’environnement GitHub
   choisi selon la branche validée, via le playbook Ansible.

Le tag mobile `latest` sur `main` et `develop` sur `develop` reste publié,
mais n’est jamais la référence passée à Ansible. La CI manuelle sert au
diagnostic et ne provoque aucun déploiement automatique ; une livraison
manuelle vérifie à nouveau la preuve CI du commit ou de l’image demandée.
L’entrée `source_run_id` est transmise automatiquement entre les deux
exécutions du workflow ; il faut la laisser vide pour un lancement manuel.
Les variables GitHub `SONAR_READY_MAIN` et `SONAR_READY_DEVELOP` doivent être
activées séparément après validation des premiers scans (voir
[SONARQUBE.md](SONARQUBE.md)). Avant cela, le workflow refuse tout déploiement
du branchement concerné, même si une CI réussit.

Ansible vérifie au moins 2,5 Gio de mémoire disponible et 10 Gio de disque libre avant de modifier la stack. Il conserve les sauvegardes, migrations additives des bases `auth` et `main`, synchronisation RBAC, contrôles JWT, contrôle 401 des routes protégées et santé publique.

Après le démarrage des dépendances, Redis et Mercure doivent atteindre l’état Docker `healthy` avant les étapes d’arrêt applicatif et de migration. Redis utilise `redis-cli ping` ; Mercure hérite de la sonde de son image sur l’API d’administration locale `/mercure/health/ready`, qui vérifie aussi le transport persistant. En cas d’échec, le déploiement s’arrête et affiche uniquement leur état `State.Health` et l’historique borné des sondes, sans inspection complète des conteneurs ni variables d’environnement. Un échec du démarrage applicatif déclenche également le diagnostic déjà prévu pour l’application et son worker.

Les compositions locale et déployée épinglent Mercure `v1.0.0` par digest et activent explicitement `protocol_version_compatibility 8` pour les clients actuels : claims JWT `mercure.publish` / `mercure.subscribe` et paramètres `topic` / `authorization`. Les signatures restent limitées à HS256 et la valeur `authorization` est masquée dans les journaux. La route publique historique `/healthz` est conservée pour les sondes externes ; elle ne remplace pas la sonde interne du transport. Le fichier Caddy standard remplace l’ancien `dev.Caddyfile` supprimé de l’image.

Ce mode transitoire conserve les règles JWT 0.x, sans exiger les nouveaux claims `iss` / `aud`. Son retrait nécessite une migration coordonnée des émetteurs de jetons et du client web vers les contrats natifs 1.x. Toute mise à jour de l’image doit vérifier publication, abonnement privé, refus d’accès, masquage des jetons et reprise de l’historique avant déploiement. Voir le [guide de migration versionné Mercure 1.0](https://github.com/dunglas/mercure/blob/v1.0.0/docs/UPGRADE.md#compatibility-mode).

`python3 scripts/check-mercure-contract.py` teste les configurations versionnées avec des clés factices et des ressources Docker isolées : santé, publication et abonnement privés, reprise sur un hub actif, refus d’accès et masquage des jetons. La CI exécute ce contrôle sans accès aux secrets applicatifs.

**Limite amont connue :** Mercure 1.0.0, comme 0.24.2, ne restitue pas l’historique Bolt immédiatement après un redémarrage tant qu’aucune nouvelle publication n’a eu lieu. Les événements restent persistés ; la borne interne `lastSeq` n’est pas restaurée à l’ouverture ([implémentation 1.0.0](https://github.com/dunglas/mercure/blob/v1.0.0/bolt.go)). Le diagnostic strict `python3 scripts/check-mercure-contract.py --check-restart-history` reproduit cet échec et ne fait pas partie du contrôle de compatibilité par défaut. Aucune publication artificielle ne masque le défaut. La reprise immédiate après redémarrage reste à corriger ou à revalider avec une version amont corrigée ; une sonde de santé verte ne la garantit pas.

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

## Fixtures de développement

Les fixtures ne sont jamais chargées par un push ou un déploiement ordinaire. Elles purgent puis reconstruisent les bases `auth` et `main`, y compris les comptes et données créés manuellement.

Pour initialiser ou réinitialiser les données de démonstration, lancer manuellement le workflow `Deploy VPS` sur la branche `develop` en activant l’entrée `reset_development_fixtures`. Le workflow construit alors une image temporaire contenant les dépendances de fixtures, sauvegarde les deux bases, arrête les processus applicatifs, applique les migrations, recharge la baseline puis exécute les contrôles de santé habituels.

La demande est refusée pour tout environnement autre que `development`. L’image applicative de production reste construite avec `--no-dev` et ne contient pas le chargeur de fixtures.

## OAuth, cookies et Stripe

- Les clients Google et Microsoft du dev utilisent uniquement les callbacks `dev.*` et restent désactivés tant que leurs identifiants dédiés ne sont pas enregistrés.
- Stripe utilise une clé, un webhook et des Price IDs en mode test propres au dev.
- Les cookies sont host-only et `Secure`, ce qui empêche leur partage entre `api.*` et `dev.api.*`.
- `CORS_ALLOW_ORIGIN` et `MERCURE_CORS_ORIGINS` n’autorisent que le frontend du même environnement.

## Rollback

Lancer manuellement `Deploy VPS` sur la branche correspondant à
l’environnement et fournir son ancienne image dans `image_ref` (tag
`sha-<SHA complet>` ou digest). Le workflow vérifie les labels OCI du dépôt et
du SHA, retrouve une CI et un gate SonarQube verts sur **cette branche et ce
commit**, puis déploie le digest résolu. Une image sans provenance ou validée
sur l’autre branche est refusée. Sans `image_ref`, la livraison manuelle
reconstruit le commit actuellement sélectionné. Les chemins, projets et
volumes étant distincts, un rollback du dev ne touche ni les conteneurs ni
les bases de production.

Les sauvegardes avant migration restent dans `VPS_APP_DIR/backups/<timestamp>/`.
