<?php

declare(strict_types=1);

if (3 !== $argc) {
  fwrite(STDERR, "Usage: php bin/check-openapi.php <committed.json> <fresh.json>\n");
  exit(2);
}

foreach ([$argv[1], $argv[2]] as $path) {
  if (!is_file($path) || !is_readable($path)) {
    fwrite(STDERR, sprintf("OpenAPI file is missing or unreadable: %s\n", $path));
    exit(2);
  }
}

if (hash_file('sha256', $argv[1]) !== hash_file('sha256', $argv[2])) {
  fwrite(STDERR, "openapi.json is stale - run: php -d memory_limit=1G bin/console api:openapi:export --output=openapi.json\n");
  exit(1);
}
