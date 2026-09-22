# FireGuard API dans Codex

La configuration du dépôt regroupe **14 skills et 20 agents spécialisés**. Les versions de
modèles ne sont pas inscrites dans les rôles : le parent résout leur catégorie et leur effort
au lancement à partir du catalogue disponible. Les profils sont dans
[agent-profiles.toml](agent-profiles.toml), avec le protocole dans le [workflow](workflow.md).

## Démarrage

Ouvrir `fireguard-sso-api/` dans Codex et démarrer une nouvelle tâche. Lire `AGENTS.md`,
le [workflow](workflow.md) et le [routage des règles](rules.md), puis le contrat du module.
Invoquer un skill avec `$nom` ou le sélecteur. Les règles sont des consignes à lire,
pas un mécanisme d'activation automatique.

L'outillage local utilise Python 3.11+ et Node.js. Le développement et les tests API demandent
les versions PHP/Composer déclarées dans `composer.json`, Make et l'infrastructure PostgreSQL
décrite dans `OPERATIONS.md`. Serena est facultatif : vérifier ses outils dans la session et
utiliser `rg` en repli s'il n'est pas connecté.

Après clone ou déplacement, depuis la racine :

```powershell
python -B .codex/scripts/configure.py
python -B .codex/scripts/configure.py --check
python -B .codex/scripts/validate.py
```

Le projet doit être reconnu comme fiable pour charger sa configuration. Examiner les hooks
avec `/hooks` dans le CLI avant activation : leur présence ne prouve pas qu'ils sont actifs.
`codex mcp list` vérifie le chargement déclaré, pas la connexion effective. Aucun de ces
fichiers ne doit modifier la confiance, les approbations ou les paramètres personnels.
Le [guide de maintenance](maintenance.md) détaille contrôles, diagnostic et anciennes invocations.

## Choisir un parcours

Chaque nom ci-dessous est complet. Les reviewers, auditeurs et explorers sont en lecture seule ;
leurs validations désignent les preuves à inspecter ou à demander au parent si une commande écrit.
Les builders reçoivent un périmètre de fichiers précis et héritent des permissions de la session.
La matrice n'impose ni délégation systématique ni lancement de tous les rôles.

| Tâche | Skill ou référence | Agent | Validation pertinente |
| --- | --- | --- | --- |
| Comprendre un module | `fg-api-explore` | `fg-api-module-explorer` | Sources, mapping auth/main, routes et contrat |
| Créer un module | `fg-api-module` | `fg-api-module-builder` | Tests ciblés, PHPStan, Deptrac, lint |
| Modèle métier ou invariant | `fg-api-domain` | `fg-api-domain-builder` | Tests Domain, PHPStan, Deptrac |
| Commande ou requête | `fg-api-usecase` | `fg-api-usecase-builder` | Tests handler, échecs/rejeu, lint |
| Port et adaptateur | `fg-api-port` | `fg-api-port-builder` | Tests du port, alias, managers, lint |
| Endpoint et DTO | `fg-api-endpoint` | `fg-api-endpoint-builder` | Succès/refus, OpenAPI, route, lint |
| Migration de schéma/données | `fg-api-migrate` | `fg-api-migration-builder` | SQL, configuration/historique, schémas auth/main |
| Couverture PHPUnit | `fg-api-tests` | `fg-api-test-writer` | Fichier ou filtre ciblé, PostgreSQL |
| Architecture et frontières | `fg-api-arch-review` | `fg-api-architecture-reviewer` | Deux analyses Deptrac, invariants observés |
| Contrat API | `fg-api-contract-review` | `fg-api-contract-reviewer` | DTO, statuts, OpenAPI et compatibilité |
| Sécurité transversale | `fg-api-security-review` | `fg-api-security-auditor` | Scénarios d'abus et preuves de refus |
| CI et déploiement | `fg-api-workflow-review` | `fg-api-workflow-reviewer` | Permissions, triggers, secrets, jobs et deux bases |
| Records, repositories, mappers, verrous | `fg-api-port` → `references/persistence.md` | `fg-api-persistence-builder` | Intégration PostgreSQL, mapping, atomicité |
| Coût des requêtes | `fg-api-arch-review` → `references/query-performance.md` | `fg-api-query-performance-reviewer` | Volumes/plans disponibles, pagination, N+1 |
| Injection et enregistrement des handlers | `fg-api-arch-review` → `references/service-wiring.md` | `fg-api-service-wiring-reviewer` | Alias, tags, transports, managers explicites |
| Authentification, sessions, MFA | `fg-api-security-review` → checklist auth | `fg-api-auth-reviewer` | Signatures, rotation, révocation, rejeu |
| RBAC et accès aux objets | `fg-api-security-review` → checklist authorization | `fg-api-authorization-reviewer` | Tenant/organisation, objet, chemins bulk et async |
| Messenger, Scheduler, outbox | `fg-api-usecase` → `references/usecase-patterns.md` | `fg-api-async-builder` | Commit/rollback, retry, reçus, leases, reprise |
| Services externes et webhooks | `fg-api-port` → `references/integrations.md` | `fg-api-integration-builder` | Doubles, délais, erreurs, signatures, doublons |
| Contrat documentaire assigné | `fg-api-module` → `references/module-docs.md` | `fg-api-module-documenter` | Sept sections, faits prouvés, liens locaux |
| Gate de qualité | `fg-api-quality` | Parent ou spécialiste déjà assigné | Contrôles proportionnés au changement |
| Contre-expertise demandée | `fg-api-codex-challenge` | Reviewer adapté à la question | Avis indépendant borné, sans récursion |

## Modèles et efforts

Les profils associent Luna aux recherches bornées, Terra aux tâches courantes, Sol aux
implémentations structurantes et Astra aux revues à fort enjeu. L'effort est propre au rôle,
pas à un numéro de modèle. Le résolveur choisit la version visible la plus récente acceptant
cet effort dans la catégorie ; il n'abaisse pas l'effort en silence.

Le parent fournit un catalogue réel et transmet le résultat à l'outil de délégation, avec un
contexte borné. Un lancement direct sans résolution hérite du modèle et de l'effort du parent.
Il n'existe pas d'alias FireGuard natif `astra-latest` ou `sol-latest`.

## Contrôles de l'outillage

```powershell
python -B .codex/scripts/validate.py
python -B .codex/scripts/configure.py --check
python -B -m unittest discover -s .codex/scripts -p 'test_*.py'
node --test .codex/hooks/adapter.test.mjs
```

Ces contrôles vérifient agents, profils, résolution simulée, skills, références et gardes.
Pour des changements limités à cet outillage, les suites applicatives ne sont pas nécessaires.
Les compteurs sont calculés à partir des fichiers, pas figés dans les validateurs.
Une nouvelle session reste nécessaire pour vérifier la découverte effective du catalogue modifié.

## Repères

Les paramètres MCP sont dans `config.toml`, les gardes et le formatage dans `hooks.json`
et `hooks/`. Ils complètent le sandbox et les approbations. Les URL locales documentées
dans `OPERATIONS.md` sont des cibles d'attachement, pas des services démarrés par Codex.

Sources officielles : [skills](https://developers.openai.com/codex/skills),
[agents](https://developers.openai.com/codex/subagents),
[hooks](https://developers.openai.com/codex/hooks),
[MCP](https://developers.openai.com/codex/mcp) et
[catalogue des modèles](https://learn.chatgpt.com/docs/app-server#models).
