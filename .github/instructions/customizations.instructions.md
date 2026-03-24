---
applyTo: ".github/skills/**/*.md,.github/agents/**/*.md,.github/prompts/**/*.md,.github/AGENTS.md,.github/copilot-instructions.md"
---

Keep repository customizations small, explicit, and easy to discover.

When creating or updating `SKILL.md`, `.agent.md`, `.prompt.md`, `AGENTS.md`, or customization indexes:

- prefer one focused responsibility per file
- keep descriptions keyword-rich because discovery depends on them
- keep examples specific to this Symfony backend rather than generic framework guidance
- preserve relative links between `.github/skills/`, `.github/agents/`, `.github/prompts/`, and `.github/AGENTS.md`

For skills:

- keep them implementation-oriented and asset-backed
- reference the nearest companion prompts or agents when that helps users choose the right entry point
- prefer reusable project patterns over generic Symfony examples

For prompts:

- keep one prompt focused on one repeatable task shape
- use prompts to orchestrate known workflows rather than to duplicate skill content
- do not pin a `model` unless there is a clear repository-specific reason to do so

For agents:

- keep one agent focused on one review or exploration persona
- prefer minimal tools; do not grant edit tools to review-only agents unless there is a clear reason
- make constraints and output format explicit so agent results stay actionable

For README and guide files:

- keep indexes aligned across `skills`, `agents`, and `prompts`
- update `.github/AGENTS.md` or the relevant index when adding a new workspace customization
- explain when to use a skill, prompt, or agent rather than listing files without context
