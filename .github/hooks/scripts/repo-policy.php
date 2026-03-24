<?php

declare(strict_types=1);

$payload = json_decode(stream_get_contents(STDIN) ?: '{}', true);
if (!is_array($payload)) {
    fwrite(STDOUT, json_encode(['continue' => true], JSON_THROW_ON_ERROR));
    exit(0);
}

$eventName = isset($payload['hookEventName']) && is_string($payload['hookEventName'])
    ? $payload['hookEventName']
    : '';

if ('SessionStart' === $eventName) {
    fwrite(STDOUT, json_encode([
        'hookSpecificOutput' => [
            'hookEventName' => 'SessionStart',
            'additionalContext' => 'Fireguard backend: keep Presentation -> Application -> Domain boundaries strict; keep business rules in handlers; preserve tenant and organization isolation; treat Auth, OAuth, Session, Otp, TrustedDevice, Authorization, and Audit as security-sensitive; avoid destructive git or shell operations.',
        ],
    ], JSON_THROW_ON_ERROR));
    exit(0);
}

if ('PreToolUse' !== $eventName) {
    fwrite(STDOUT, json_encode(['continue' => true], JSON_THROW_ON_ERROR));
    exit(0);
}

$toolName = isset($payload['tool_name']) && is_string($payload['tool_name']) ? $payload['tool_name'] : '';
$toolInput = isset($payload['tool_input']) && is_array($payload['tool_input']) ? $payload['tool_input'] : [];

$terminalCommand = findTerminalCommand($toolInput);
if (null !== $terminalCommand && isDangerousCommand($terminalCommand)) {
    fwrite(STDOUT, json_encode([
        'hookSpecificOutput' => [
            'hookEventName' => 'PreToolUse',
            'permissionDecision' => 'deny',
            'permissionDecisionReason' => 'Destructive terminal command blocked by repository policy.',
            'additionalContext' => 'Use non-destructive inspection commands or ask the user before any destructive git or shell action.',
        ],
    ], JSON_THROW_ON_ERROR));
    exit(0);
}

if (isPotentialWriteTool($toolName) && touchesSensitivePath($toolInput)) {
    fwrite(STDOUT, json_encode([
        'hookSpecificOutput' => [
            'hookEventName' => 'PreToolUse',
            'permissionDecision' => 'ask',
            'permissionDecisionReason' => 'This operation touches high-risk customization, workflow, or security-related files.',
            'additionalContext' => 'Confirm that the change is intentional and stays aligned with repository security and customization rules.',
        ],
    ], JSON_THROW_ON_ERROR));
    exit(0);
}

fwrite(STDOUT, json_encode([
    'hookSpecificOutput' => [
        'hookEventName' => 'PreToolUse',
        'permissionDecision' => 'allow',
    ],
], JSON_THROW_ON_ERROR));

function findTerminalCommand(array $toolInput): ?string
{
    foreach (['command', 'text', 'input'] as $key) {
        if (isset($toolInput[$key]) && is_string($toolInput[$key]) && '' !== trim($toolInput[$key])) {
            return $toolInput[$key];
        }
    }

    if (isset($toolInput['args']) && is_array($toolInput['args'])) {
        $parts = array_filter($toolInput['args'], static fn (mixed $value): bool => is_scalar($value) && '' !== trim((string) $value));
        if ([] !== $parts) {
            return implode(' ', array_map(static fn (mixed $value): string => (string) $value, $parts));
        }
    }

    return null;
}

function isDangerousCommand(string $command): bool
{
    $normalized = strtolower(str_replace('\\', '/', $command));
    $patterns = [
        '/\bgit\s+reset\s+--hard\b/',
        '/\bgit\s+checkout\s+--\b/',
        '/\bgit\s+clean\s+-f(?:d|x|dx|xdf|fdx)\b/',
        '/\brm\s+-rf\s+(?:\/|\.\/?|\*|~)/',
        '/\bremove-item\b.*\b-recurse\b.*\b-force\b/',
        '/\bdel\b.*\/(?:f|s|q)/',
        '/\brmdir\b.*\/(?:s|q)/',
    ];

    foreach ($patterns as $pattern) {
        if (1 === preg_match($pattern, $normalized)) {
            return true;
        }
    }

    return false;
}

function isPotentialWriteTool(string $toolName): bool
{
    $normalized = strtolower($toolName);
    if ('' === $normalized) {
        return false;
    }

    foreach (['edit', 'write', 'create', 'replace', 'delete', 'rename', 'move', 'patch'] as $keyword) {
        if (str_contains($normalized, $keyword)) {
            return true;
        }
    }

    return in_array($normalized, ['create_file', 'replace_string_in_file', 'apply_patch'], true);
}

function touchesSensitivePath(array $toolInput): bool
{
    $sensitivePaths = [
        '.github/hooks/',
        '.github/agents/',
        '.github/prompts/',
        '.github/skills/',
        '.github/instructions/',
        '.github/agents.md',
        '.github/copilot-instructions.md',
        '.github/workflows/',
        'config/packages/security.yaml',
        'config/packages/rate_limiter.yaml',
        'config/packages/test/rate_limiter.yaml',
    ];

    foreach (flattenStrings($toolInput) as $value) {
        $normalized = strtolower(str_replace('\\', '/', $value));
        foreach ($sensitivePaths as $path) {
            if (str_contains($normalized, strtolower($path))) {
                return true;
            }
        }
    }

    return false;
}

/**
 * @return list<string>
 */
function flattenStrings(mixed $value): array
{
    if (is_string($value)) {
        return [$value];
    }

    if (!is_array($value)) {
        return [];
    }

    $strings = [];
    foreach ($value as $item) {
        array_push($strings, ...flattenStrings($item));
    }

    return $strings;
}
