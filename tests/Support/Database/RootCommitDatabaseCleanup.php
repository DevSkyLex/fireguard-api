<?php

declare(strict_types=1);

namespace Tests\Support\Database;

use InvalidArgumentException;
use PDO;
use PDOException;

use function is_array;
use function ltrim;
use function parse_url;
use function preg_match;
use function sprintf;
use function str_contains;
use function urldecode;
use function usleep;

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
   * Retries only a transient Windows socket collision before the connection has opened.
   *
   * @access public
   *
   * @param string $url the dedicated clone URL
   * @param (callable(): PDO)|null $openMaintenanceConnection the optional test connection factory
   * @param (callable(int): void)|null $pause the optional test pause callback, receiving microseconds
   *
   * @return void
   */
  public static function drop(string $url, ?callable $openMaintenanceConnection = null, ?callable $pause = null): void
  {
    $name = self::ownedDatabaseName($url);
    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['host'])) {
      throw new InvalidArgumentException('A test clone requires a PostgreSQL host.');
    }
    $openMaintenanceConnection ??= static fn (): PDO => new PDO(
      sprintf('pgsql:host=%s;port=%d;dbname=postgres', $parts['host'], (int) ($parts['port'] ?? 5432)),
      isset($parts['user']) ? urldecode($parts['user']) : null,
      isset($parts['pass']) ? urldecode($parts['pass']) : null,
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $pause ??= static function (int $microseconds): void {
      usleep($microseconds);
    };
    $maintenance = null;
    for ($attempt = 1; null === $maintenance; ++$attempt) {
      try {
        $maintenance = $openMaintenanceConnection();
      } catch (PDOException $error) {
        if (3 <= $attempt || !self::isTransientWindowsConnectionFailure($error)) {
          throw $error;
        }
        $pause(100_000);
      }
    }
    $maintenance->exec(sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', $name));
  }

  /**
   * Method isTransientWindowsConnectionFailure
   *
   * Identifies only the observed Windows socket collision while opening the maintenance connection.
   *
   * @access private
   *
   * @param PDOException $error the failed connection attempt
   *
   * @return bool whether a bounded retry may open the connection
   */
  private static function isTransientWindowsConnectionFailure(PDOException $error): bool
  {
    $isConnectionFailure = '08006' === $error->getCode()
      || '08006' === ($error->errorInfo[0] ?? null)
      || str_contains($error->getMessage(), 'SQLSTATE[08006]');

    return $isConnectionFailure && str_contains($error->getMessage(), '(0x00002740/10048)');
  }
  // #endregion
}
