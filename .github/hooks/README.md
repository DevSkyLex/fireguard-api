# Hooks Index

This folder contains workspace hooks for deterministic agent policy enforcement.

## Configured Hooks

- [policy.json](./policy.json): shared hook configuration for session context injection and pre-tool safety checks.

## Current Behavior

### SessionStart

- injects a short project policy reminder for the current agent session
- reinforces the repository's strict architecture, scoping, and security expectations

### PreToolUse

- blocks destructive terminal commands such as hard resets, forced cleans, and mass recursive deletions
- asks for confirmation before editing high-risk files such as hooks, skills, prompts, agents, workflow files, and security-related config

## Script

- [repo-policy.php](./scripts/repo-policy.php): shared PHP hook script that reads hook input from stdin and returns JSON decisions to VS Code

## Testing

1. Open the Output panel and select `GitHub Copilot Chat Hooks`.
2. Start a new chat session and confirm the `SessionStart` hook injects context.
3. Attempt a destructive terminal command such as `git reset --hard` and confirm the hook blocks it.
4. Attempt to edit a sensitive customization or security file and confirm the hook asks for approval.

## Notes

- Hooks are deterministic and should stay small, auditable, and easy to reason about.
- The hook script assumes `php` is available in the workspace environment, which is a reasonable project assumption for this Symfony repository.
