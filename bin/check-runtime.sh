#!/bin/sh
set -eu

# PHP2Shell fixes must be present in both the CLI used by workers and FrankenPHP.
php -r '
  printf("PHP CLI %s\n", PHP_VERSION);
  if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4 || version_compare(PHP_VERSION, "8.4.27", "<")) {
    fwrite(STDERR, "PHP 8.4.27 or a later PHP 8.4 patch is required.\n");
    exit(1);
  }
'

frankenphp_version=$(frankenphp version)
printf '%s\n' "$frankenphp_version"
php -r '
  if (!preg_match("/^FrankenPHP v1[.]13[.]2 PHP (8[.]4[.][0-9]+) /", $argv[1], $matches)
      || version_compare($matches[1], "8.4.27", "<")) {
    fwrite(STDERR, "FrankenPHP 1.13.2 with PHP 8.4.27 or a later PHP 8.4 patch is required.\n");
    exit(1);
  }
' "$frankenphp_version"

. /etc/os-release
printf '%s\n' "$PRETTY_NAME"
test "$ID" = debian && test "${VERSION_CODENAME:-}" = bookworm
