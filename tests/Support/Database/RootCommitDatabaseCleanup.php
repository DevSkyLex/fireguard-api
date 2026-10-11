<?php

declare(strict_types=1);

namespace Tests\Support\Database;

use InvalidArgumentException;
use PDO;

use function is_array;
use function ltrim;
use function parse_url;
use function preg_match;
use function sprintf;
use function urldecode;

/**
 * Class RootCommitDatabaseCleanup
 *
 * Removes only the dedicated per-test clones used by HTTP flows that must own a real commit.
 *
 * @category Test Support
 */
final readonly class RootCommitDatabaseCleanup
{
  // #region Methods
  /**
   * Method ownedDatabaseName
   *
   * Rejects production databases, templates and ordinary process clones before any connection is opened.
   *
   * @access public
   *
   * @param string $url the dedicated clone URL
   *
   * @return string the strictly scoped clone identifier
   */
  public static function ownedDatabaseName(string $url): string
  {
    $parts = parse_url($url);
    $name = is_array($parts) ? urldecode(ltrim($parts['path'] ?? '', '/')) : '';
    if (1 !== preg_match('/^[A-Za-z0-9_]+_(?:test|e2e)_wrootcommit_[a-f0-9]{16}$/D', $name)) {
      throw new InvalidArgumentException('Cleanup requires a dedicated root-commit test database clone.');
    }

    return $name;
  }

  /**
   * Method drop
   *
   * Drops the checked disposable clone after the test kernel has shut down.
   *
   * @access public
   *
   * @param string $url the dedicated clone URL
   *
   * @return void
   */
  public static function drop(string $url): void
  {
    $name = self::ownedDatabaseName($url);
    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['host'])) {
      throw new InvalidArgumentException('A test clone requires a PostgreSQL host.');
    }
    $maintenance = new PDO(
      sprintf('pgsql:host=%s;port=%d;dbname=postgres', $parts['host'], (int) ($parts['port'] ?? 5432)),
      isset($parts['user']) ? urldecode($parts['user']) : null,
      isset($parts['pass']) ? urldecode($parts['pass']) : null,
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $maintenance->exec(sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', $name));
  }
  // #endregion
}
