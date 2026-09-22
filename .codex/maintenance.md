# Maintenir l'outillage Codex API

Ce guide concerne les fichiers du dépôt. Il ne modifie ni les paramètres personnels,
ni la confiance du projet, ni la configuration d'un autre client. `AGENTS.md`,
`ARCHITECTURE.md`, `SECURITY.md` et les contrats des modules restent normatifs.

## Installation et déplacement

Les skills sont versionnés dans `.agents/skills/`, les rôles dans `.codex/agents/`.
Aucun package de design tiers n'est requis côté API. Python 3.11+ et Node.js suffisent aux
contrôles d'outillage ; les prérequis applicatifs sont dans `composer.json`, `Makefile` et
`OPERATIONS.md`. Serena est facultatif, avec recherche locale en repli.

Après clone ou création d'un worktree, vérifier puis adapter uniquement les chemins MCP locaux :

```powershell
python -B .codex/scripts/configure.py --check
python -B .codex/scripts/configure.py
python -B .codex/scripts/configure.py --check
```

Lire le diff de `config.toml` avant activation. Ne pas y ajouter `model`,
`model_reasoning_effort`, `approval_policy`, `sandbox_mode` ou une section `projects`.
Les politiques personnelles et de session ne sont pas gérées par ce dépôt. Examiner les
hooks dans le client qui les prend en charge ; leur présence ne prouve pas leur activation.
Une configuration MCP déclarée ne prouve pas davantage sa connexion effective.

## Profils et résolution

[agent-profiles.toml](agent-profiles.toml) est une convention FireGuard. Chaque table
`[agents."nom-du-role"]` contient `category` et `effort`. Chaque rôle a exactement un profil,
sans profil orphelin. Catégories : `astra`, `sol`, `terra`, `luna` ; efforts utilisés :
`medium`, `high`, `xhigh`. Le modèle réel doit aussi accepter l'effort demandé.

Les TOML natifs n'ont ni `model` ni `model_reasoning_effort`. Le parent normalise le catalogue
de la session ou toutes les pages de `model/list`, puis lance par exemple :

```powershell
Get-Content -Raw -LiteralPath 'catalogue-normalise.json' | python -B .codex/scripts/resolve_agent.py --agent fg-api-module-explorer
```

Le fichier d'exemple représente un catalogue temporaire fourni par le parent ; ne pas en
versionner une copie qui vieillirait. Le format exact et le lancement avec contexte borné
sont dans le [workflow](workflow.md). Le résolveur trouve les profils depuis son propre
emplacement, indépendamment du répertoire courant, et ne contacte aucun service.

Transmettre `model` et `reasoning_effort` retournés à l'outil de délégation. En cas d'erreur,
corriger le catalogue ou le profil : ne pas fabriquer d'alias `latest`, changer de catégorie
ou réduire implicitement l'effort. Une évolution de nomenclature se traite dans le résolveur
et ses tests. Un lancement direct hérite du parent, sans résolution automatique.

## Contrôles et intégrité

Depuis la racine :

```powershell
python -B .codex/scripts/validate.py
python -B .codex/scripts/configure.py --check
python -B -m unittest discover -s .codex/scripts -p 'test_*.py'
node --test .codex/hooks/adapter.test.mjs
```

Le validateur contrôle manifests, liens, skills, rôles, profils et paramètres globaux interdits.
Les tests du résolveur utilisent des catalogues simulés, jamais un modèle réel. Les reviewers,
auditeurs et explorers restent en lecture seule ; les autres héritent des permissions de
session. Les compteurs sont dynamiques : la cible actuelle est **14 skills et 20 agents**,
sans assertion figée dans le validateur.

Préserver les gardes sur secrets, migrations historiques, arbres générés et opérations Git
destructrices. Inspecter source et destination d'un déplacement. Les hooks ne remplacent pas
le sandbox ; tester les protections avec leurs fixtures, sans ouvrir de fichier secret.

Les branches de travail Codex suivent `codex/<description-kebab>` : minuscules, chiffres et
mots séparés par un seul tiret. Le garde Codex, le hook Git `pre-push` et la CI acceptent ce
préfixe tout en rejetant les descriptions invalides. `codex` n'est pas un type de commit :
conserver les types Conventional Commits existants.

Après évolution d'un skill, vérifier appels et références conditionnelles avec un parcours
réaliste borné. Après modification du catalogue, démarrer une nouvelle session et vérifier la
découverte effective des rôles et skills ; la session courante peut conserver l'ancien catalogue.
Une vérification statique ne prouve pas ce rechargement.

## Migration des anciennes invocations

Migration directe : les entrées ci-dessous sont supprimées, sans alias. Les noms historiques
servent uniquement à retrouver le propriétaire actuel et sa référence conditionnelle.

| Ancien skill | Parcours actuel |
| --- | --- |
| `fg-api-api-platform-contract` | `fg-api-endpoint` → [api-platform-contract.md](../.agents/skills/fg-api-endpoint/references/api-platform-contract.md) |
| `fg-api-dual-database` | `fg-api-migrate` → [dual-database.md](../.agents/skills/fg-api-migrate/references/dual-database.md) |
| `fg-api-hexagonal-layout` | Référence transversale [hexagonal-layout.md](references/hexagonal-layout.md) |
| `fg-api-module-md` | `fg-api-module` → [module-docs.md](../.agents/skills/fg-api-module/references/module-docs.md) |
| `fg-api-module-testing` | `fg-api-tests` → [testing.md](../.agents/skills/fg-api-tests/references/testing.md) |
| `fg-api-security-checklist` | `fg-api-security-review` → [security-checklist.md](../.agents/skills/fg-api-security-review/references/security-checklist.md) |
| `fg-api-usecase-patterns` | `fg-api-usecase` → [usecase-patterns.md](../.agents/skills/fg-api-usecase/references/usecase-patterns.md) |

La disponibilité LSP est intégrée à [lsp-usage.md](rules/lsp-usage.md). Les rôles portent
tous le préfixe `fg-api-`, notamment `fg-api-endpoint-builder` et `fg-api-usecase-builder`.
Les spécialistes réutilisent les skills conservés ; ils ne nécessitent pas chacun un skill.

## Diagnostic

| Symptôme | Action |
| --- | --- |
| Skill ou agent absent | Vérifier nom/fichier, lancer le validateur, tester dans une nouvelle session |
| Catalogue incomplet, ambigu ou modèle introuvable | Reprendre toutes les pages et champs réels ; garder l'effort demandé |
| Paramètres explicites refusés par la délégation | Utiliser `fork_turns="none"` ou un nombre borné de tours avec le contexte manquant |
| MCP déclaré mais absent | Vérifier les outils exposés, poursuivre avec `rg`, annoncer le repli |
| Référence manquante après consolidation | Corriger le consommateur, sans recréer un alias |
| Container PHP hors mémoire | Utiliser `php -d memory_limit=1G bin/console`, comme le Makefile |
| Tests PostgreSQL non préparés | Lire les [procédures de test](../.agents/skills/fg-api-tests/references/testing.md) ; `make test-db` remplace les templates |
| Formatage hors périmètre | `make cs-fix` est global ; préférer des chemins explicites ou `make cs-lint` |

Les changements documentaires et d'outillage utilisent ces contrôles sans lancer les suites
applicatives. Si un contrôle n'est pas disponible, rapporter sa cause et les preuves obtenues.
