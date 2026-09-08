# FireGuard API dans Codex

Cette configuration est autonome. Codex lit `AGENTS.md`, `.codex/workflow.md`, les
règles locales applicables et les skills de `.agents/skills/`. Aucun agent, skill ou
hook Codex ne dépend de la configuration d'un autre client.

## Activer

Ouvrir `fireguard-sso-api/` dans Codex et démarrer une nouvelle tâche.
Invoquer les skills avec `$nom` ou via le sélecteur. Les noms API/Web sont préfixés
pour éviter les collisions. Les agents natifs sont dans `.codex/agents/`, sans
modèle imposé ; les reviewers sont configurés en lecture seule.

Le projet doit être approuvé comme fiable pour charger sa configuration locale.
Examiner et approuver les hooks avec `/hooks` dans le CLI. Ils ne sont PAS actifs
simplement parce que leurs fichiers existent. Aucun réglage de confiance, modèle,
sandbox, approbation ou configuration utilisateur n'a été modifié.

## Organisation

| Élément | Emplacement |
| --- | --- |
| instructions | `AGENTS.md` + [workflow Codex](workflow.md) |
| procédures | skills autonomes dans `.agents/skills/` |
| agents | rôles TOML natifs dans `agents/` |
| règles par chemin | [routage explicite](rules.md) avant édition |
| MCP | `config.toml` local au projet |
| garde et formatage | `hooks.json` + scripts locaux dans `hooks/` |

Les contre-expertises restent explicites et bornées : aucun `codex exec` imbriqué,
aucune récursion et aucun modèle imposé.

## Skills

- `$fg-api-arch-review`
- `$fg-api-contract-review`
- `$fg-api-domain`
- `$fg-api-endpoint`
- `$fg-api-explore`
- `$fg-api-migrate`
- `$fg-api-module`
- `$fg-api-port`
- `$fg-api-quality`
- `$fg-api-security-review`
- `$fg-api-tests`
- `$fg-api-usecase`
- `$fg-api-workflow-review`
- `$fg-api-api-platform-contract`
- `$fg-api-codex-challenge`
- `$fg-api-dual-database`
- `$fg-api-hexagonal-layout`
- `$fg-api-module-md`
- `$fg-api-module-testing`
- `$fg-api-security-checklist`
- `$fg-api-usecase-patterns`

## Connexions et portabilité

Les chemins MCP de `config.toml` sont liés au checkout. Après clone, déplacement ou
création d'un worktree, les actualiser avant activation :

```powershell
python .codex/scripts/configure.py
python .codex/scripts/configure.py --check
```

Prérequis : Node.js, Python 3.11+, PHP/Composer et `serena` sur PATH. Depuis le
checkout, `codex mcp list` vérifie le chargement de la configuration, pas la connexion
effective. Une panne MCP doit être annoncée et une recherche locale peut servir de repli.

## Hooks et validation

`node --test .codex/hooks/adapter.test.mjs` vérifie les patches sans modifier le code
métier. Le hook inspecte source et destination lors d'un déplacement, applique les
gardes locaux, puis formate le PHP touché avec le binaire du projet.

Ces gardes ne remplacent pas le sandbox ni les approbations. Les règles et exclusions
restent obligatoires même si les hooks ne sont pas chargés.

## Vérification

```powershell
python .codex/scripts/validate.py
python .codex/scripts/configure.py --check
node --test .codex/hooks/adapter.test.mjs
```

Le validateur contrôle les manifests, les agents, les skills, leurs références et
l'absence de chemins hérités. Pour une modification limitée à cet outillage, ces
contrôles remplacent les tests métier PHP.

## Développement

API : http://localhost:8000 ; Mailpit : http://localhost:8025 ; Mercure :
http://localhost:3000. Ce sont des cibles d'attachement, pas des services démarrés.
Lire Makefile et OPERATIONS.md pour le lancement de l'infrastructure.

## Sources officielles

- [Skills](https://developers.openai.com/codex/skills)
- [Agents](https://developers.openai.com/codex/subagents)
- [Hooks](https://developers.openai.com/codex/hooks)
- [MCP](https://developers.openai.com/codex/mcp)
