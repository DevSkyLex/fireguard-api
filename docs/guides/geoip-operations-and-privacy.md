# GeoIP : exploitation et protection des données

Cette procédure concerne les opérateurs et responsables du traitement. La collecte reste
désactivée par défaut. Elle ajoute le pays et éventuellement la ville approximative d'une IP
publique aux sessions créées et aux emails de sécurité existants. Elle ne suit pas les déplacements
et ne décide pas de l'accès. La base locale ne reçoit aucune IP d'utilisateur chez DB-IP.

## Avant activation

Formaliser l'intérêt poursuivi, la nécessité et la mise en balance, en comparant notamment
un affichage limité au pays avec l'ajout de la ville. L'intérêt légitime est une base envisagée,
pas une validation automatique. Conserver l'analyse, sa date, son responsable et la décision
d'activation dans le registre interne. Si la ville n'est pas justifiée, maintenir le traitement
désactivé jusqu'à adaptation du périmètre. Voir la [méthode CNIL](https://www.cnil.fr/fr/les-bases-legales/interet-legitime).

Renseigner l'identité du responsable, le contact pour les droits, les prestataires email et
hébergement, leurs pays et garanties de transfert, et leurs durées effectives. Publier la notice
complétée dans l'information de confidentialité accessible aux utilisateurs. La mention du
panneau et l'attribution ne remplacent pas cette information.

## Configuration et chaîne proxy

| Variable | Valeur initiale | Effet |
| --- | --- | --- |
| `GEOIP_ENABLED` | `false` | Aucun enrichissement tant que désactivé |
| `GEOIP_DATABASE_PATH` | `var/geoip/dbip-city-lite.mmdb` hors production | Fichier MMDB local privé |
| `GEOIP_MAX_AGE_DAYS` | `45` | Suspend les nouveaux enrichissements si la date de build est trop ancienne |
| `TRUSTED_PROXIES` | vide | IP/CIDR explicites des seuls proxies réellement utilisés |

En production, Compose fixe le chemin `/var/lib/fireguard/geoip/dbip-city-lite.mmdb` dans
`geoip_data`. L'application monte ce volume en lecture seule ; `geoip_maintenance` le monte
en écriture. Le répertoire est `0750`, le fichier publié `0640`, propriétaire `1000:1000`.
Le déploiement initialise ces permissions. Ne pas exposer ce répertoire via HTTP, ni le versionner.
Le workflow transmet les variables GitHub non secrètes `GEOIP_ENABLED` (défaut `false`),
`GEOIP_MAX_AGE_DAYS` (défaut `45`) et `TRUSTED_PROXIES` (défaut vide). Le déploiement géré
les conserve dans l'environnement ; une liste de proxies vide est volontairement acceptée.
Avec `FIREGUARD_MANAGED_ENV=false`, ajouter explicitement ces trois clés au fichier
d'exploitation avant le déploiement, conformément au contrôle des clés requises.
Dans les deux modes, Ansible valide la valeur `TRUSTED_PROXIES` effectivement résolue pour
le service `app` par Compose, avant tout arrêt de service. Seules une liste vide ou des
IP/CIDR explicites en CSV sans espaces sont acceptées, par exemple
`192.0.2.1,2001:db8::/48`. Les valeurs non vides ne sont pas normalisées : Symfony conserve
les espaces des champs CSV non entourés de guillemets. Les alias `REMOTE_ADDR`, `private_ranges` et
`PRIVATE_SUBNETS`, les noms DNS, les éléments vides et les adresses invalides sont refusés,
y compris dans une liste ou entre guillemets. La configuration résolue reste sous `no_log` ;
ne pas la copier dans un diagnostic ou un ticket.

Identifier les adresses réelles de Traefik et les stabiliser dans son réseau avant de remplir
`TRUSTED_PROXIES`. Ne pas faire confiance à toutes les plages privées, à tout le réseau Docker,
ou automatiquement à `REMOTE_ADDR`. L'application n'a pas de port publié dans Compose production.
Vérifier depuis le vrai ingress que Traefik assainit les en-têtes, que `getClientIp()` retrouve
l'adresse publique du client, et qu'un accès direct ou un `X-Forwarded-For` falsifié ne peut
la remplacer. Les tests unitaires ne prouvent pas la configuration réelle du VPS.
Voir [Symfony et les proxies](https://symfony.com/doc/7.4/deployment/proxies.html).

## Mise à jour, surveillance et conservation

Installation initiale sur l'hôte PHP :

```sh
php -d memory_limit=1G bin/console app:geoip:update
php -d memory_limit=1G bin/console app:geoip:update --check
php -d memory_limit=1G bin/console app:geoip:purge-session-locations
php -d memory_limit=1G bin/console app:cleanup:auth-data --dry-run
```

Sur le serveur déployé (le fichier production est installé sous `compose.yaml`) :

```sh
docker compose --profile tools run --rm --no-deps geoip_maintenance app:geoip:update
docker compose --profile tools run --rm --no-deps geoip_maintenance app:geoip:update --check
```

Le téléchargement utilise uniquement l'URL HTTPS officielle DB-IP, sans redirection :
`https://download.db-ip.com/free/dbip-city-lite-YYYY-MM.mmdb.gz`. Il est borné à 120 secondes,
256 Mio compressés et 1 Gio décompressé, avec 30 secondes pour l'expansion. Le verrou est dans
le volume partagé. Gzip (CRC et longueur), métadonnées MMDB et recherches IPv4/IPv6 sont validés
avant remplacement atomique. Une édition mensuelle valide déjà installée est ignorée ;
`--force` permet sa réinstallation. Un échec conserve le dernier fichier installé.

Ansible exige un exécutable `crontab` et un service `cron`/`crond` actif avant toute modification
de service. Il installe, sous l'utilisateur de déploiement, le script
`fireguard-maintenance.sh` en mode `0750` et trois tâches indépendantes avant tout arrêt :

| Heure de l'hôte | Tâche du script | Opération |
| --- | --- | --- |
| 03:17 | `geoip-update` | Mise à jour, puis contrôle de fraîcheur |
| 03:37 | `geoip-purge` | Effacement des lieux des sessions révoquées |
| 03:47 | `auth-retention` | Rétention auth via le service `app` |

La mise à jour et le contrôle utilisent `--if-enabled` et sont ignorés lorsque la collecte
est désactivée. La purge des lieux et la rétention restent actives et leurs échecs ne se
bloquent pas mutuellement. Une absence d'édition au début du mois peut produire un échec
transitoire ; la tâche réessaie le lendemain. Ne pas lancer un deuxième téléchargement manuel
pour contourner le verrou. Le déploiement conserve aussi la purge avant réouverture du trafic.

Lors d'un rollback vers une révision antérieure à GeoIP, conserver le script installé et ses
trois crons. Les anciennes procédures de déploiement copient les fichiers Compose connus sans
supprimer ce script. À chaque exécution, celui-ci vérifie les fichiers Compose en place :
une configuration valide sans `geoip_maintenance` ignore les deux tâches GeoIP ; la rétention
continue dans `app` avec l'entrée `php`, une mémoire de `1G` et `--env=prod`. Une configuration
Compose invalide ou une commande en échec conserve une sortie non nulle et journalise un
message fixe, sans contenu d'environnement. Rejouer les trois tâches après le rollback pour
vérifier ce comportement ; ne pas les remplacer par un cron couplant purge et rétention.

Raccorder les erreurs `fireguard-maintenance` du journal système et les sorties/échecs des tâches
au monitoring de l'opérateur. Contrôler aussi quotidiennement `app:geoip:update --check --if-enabled`.
Les sorties indiquent l'état et la date de build, sans les IP recherchées ni les résultats.
Ne pas ajouter d'IP ou de géographie aux logs applicatifs, traces, métriques ou tickets de support.
Les erreurs des emails portant le contexte géographique exposent seulement leur type :
un message de transport peut reprendre du contenu délivré. Ne pas activer de capture de
contenu email ou de contexte de requête en production dans un profiler ou outil de traces.
Les utilisateurs continuent à s'authentifier et à recevoir leurs emails si la base est indisponible.

| Données | Conservation de cette implémentation |
| --- | --- |
| Pays/ville d'une session active | Pendant la conservation de cette session, sans recalcul |
| Session révoquée | Effacement des clés pays/ville dans la même opération durable |
| Anciennes sessions déjà révoquées | Nettoyage au déploiement, puis quotidien |
| Session inactive | Purge auth existante : actuellement 90 jours sans activité, à justifier |
| Contexte GeoIP des emails | Temporaire en mémoire ; absent de Notification persistante, de l'audit et de Mercure |
| Email délivré et copies du prestataire | Durées propres à renseigner dans le registre et les contrats |
| Sauvegardes auth | Rétention opérationnelle documentée, accès limité et procédure de restauration ci-dessous |

Les 90 jours proviennent du paramètre de rétention existant, pas d'une obligation générale
du RGPD. Documenter et réexaminer les durées selon la [CNIL](https://www.cnil.fr/fr/passer-laction/les-durees-de-conservation-des-donnees).

Après restauration, garder le service fermé au trafic, rejouer les effacements intervenus
depuis la sauvegarde depuis le registre sécurisé des demandes (identifiants et portée, sans
copier les lieux), purger les localisations révoquées puis exécuter la rétention auth. Vérifier
avant réouverture qu'aucune donnée effacée ne redevient visible. Une suppression dans la base
courante ne réécrit pas toutes les sauvegardes historiques ni les emails déjà délivrés.

## Notice d'information à compléter et publier

> **Lieu approximatif des connexions et demandes de sécurité.** [Responsable du traitement]
> déduit le pays et, lorsqu'elle est disponible, la ville approximative de l'adresse IP utilisée
> à la connexion ou lors d'une demande de code email ou de changement d'adresse email. Ces
> informations vous aident à reconnaître vos connexions et demandes de sécurité. Un VPN ou
> un réseau mobile peut indiquer un autre lieu. Elles ne permettent pas de vous localiser
> précisément et ne déterminent pas automatiquement votre accès.
>
> Ce traitement repose sur [intérêt légitime retenu et validé]. Le calcul s'effectue sur notre
> serveur à partir d'une base DB-IP locale ; l'adresse IP recherchée n'est pas transmise à DB-IP.
> Les destinataires sont vous-même, les intervenants habilités en cas de support nécessaire,
> [hébergeur] et [prestataire email] pour les emails concernés. [Préciser les pays, transferts et garanties].
>
> Le lieu d'une session est conservé avec celle-ci et effacé lorsqu'elle est révoquée. Les sessions
> inactives sont supprimées après [durée validée, configuration actuelle 90 jours]. Le contexte
> d'envoi n'ajoute pas d'historique géographique dans l'application ; les emails délivrés et les
> sauvegardes suivent [durées et procédures applicables].
>
> Pour exercer vos droits d'accès, rectification/contestation, effacement ou opposition fondée
> sur votre situation, contactez [contact des droits/DPO]. [Responsable] vous répond selon la
> procédure de protection des données. Vous pouvez adresser une réclamation à la CNIL ou à
> votre autorité de contrôle compétente.

## Entrée au registre à finaliser

| Rubrique | Contenu à consigner |
| --- | --- |
| Traitement | Reconnaissance des connexions et demandes de sécurité des comptes FireGuard |
| Responsable/contact | Identité, coordonnées et DPO/contact des droits à compléter |
| Personnes | Titulaires de comptes et destinataires de leurs demandes email |
| Données/source | IP de connexion déjà traitée, pays ISO et ville approximative déduits localement |
| Base envisagée | Intérêt légitime, analyse datée de nécessité et de mise en balance, avec justification distincte de la ville |
| Destinataires | Titulaire ; support strictement habilité et nécessaire ; hébergeur ; prestataire email identifié |
| Transferts | Pays et garanties contractuelles à vérifier pour l'hébergement et l'email |
| Durées | Tableau ci-dessus, durées effectives des prestataires/sauvegardes et justification de la rétention auth |
| Mesures | Ports applicatifs, contrôle du titulaire, base privée locale, collecte désactivable, échappement, effacement atomique, aucun log GeoIP |
| Droits | Procédure manuelle existante, contrôle de l'identité et de l'habilitation, correction/effacement et traitement de l'opposition |
| Réexamen | Changement de finalité, données, prestataire, périmètre ou durée ; responsable et date de revue |

L'accès API est réservé au titulaire ; ce changement ne crée pas d'écran ni d'endpoint de support.
Toute intervention privilégiée suit la procédure existante, avec habilitation et accès minimal.
Pour une contestation, expliquer l'approximation et effacer l'information si nécessaire ; ne pas
remplacer la ville par une adresse exacte ni prétendre corriger les données mondiales de DB-IP.

Pour un effacement autorisé :

```sh
php -d memory_limit=1G bin/console app:geoip:purge-session-locations --user-id='<UUID_VERIFIE>'
```

Cette commande efface les lieux des sessions de ce compte, sans révoquer ses sessions et sans
modifier les autres métadonnées. Elle n'empêche pas les enrichissements d'une connexion future.
L'opposition exige donc une décision suivie d'une cessation effective : cette version ne comporte
pas d'exclusion par compte. Si l'opposition est accueillie, désactiver globalement `GEOIP_ENABLED`,
redémarrer les processus applicatifs pour prendre en compte la configuration, puis effacer les
lieux du compte et organiser les demandes auprès des prestataires concernés. Ne pas présenter
un simple effacement comme une opposition satisfaite. Consigner la décision et son exécution
dans le registre sécurisé existant, sans enregistrer les lieux.

Avant ouverture de la collecte : valider l'analyse et la notice, tester la vraie chaîne proxy,
installer et vérifier la base initiale, confirmer les trois tâches quotidiennes et leurs alertes,
tester l'effacement et la procédure de restauration, puis activer le réglage et redémarrer les
processus. Une désactivation ne purge pas automatiquement les instantanés de sessions actives.

## Licence et attribution

DB-IP City Lite est actualisée mensuellement et distribuée sous CC BY 4.0. Le panneau et les
emails affichant un lieu portent un lien « Géolocalisation par DB-IP ». Le lecteur PHP
`maxmind-db/reader` possède sa propre licence Apache-2.0. Conserver l'attribution lors de
toute nouvelle présentation de ces données. Voir les [conditions DB-IP](https://db-ip.com/db/download/ip-to-city-lite).
