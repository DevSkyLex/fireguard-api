<?php

declare(strict_types=1);

namespace Tests\Support\Database;

use Closure;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use LogicException;
use PHPUnit\Framework\Attributes\{After, Before};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

use function bin2hex;
use function function_exists;
use function is_string;
use function random_bytes;

/**
 * Trait RootCommitDatabaseIsolationTrait
 *
 * Provides pristine seeded databases for each true-commit HTTP test without disabling ordinary suite isolation.
 * Pair it with DAMA's SkipDatabaseRollback attribute on the selected test class.
 *
 * @category Test Support
 *
 * @phpstan-require-extends KernelTestCase
 */
trait RootCommitDatabaseIsolationTrait
{
  // #region Properties
  /**
   * Property rootCommitOriginalUrls
   *
   * @var array<string, array{env: mixed, server: mixed}> original process-scoped environment values
   */
  private array $rootCommitOriginalUrls = [];

  /**
   * Property rootCommitCloneUrls
   *
   * @var list<string> disposable per-test clones, never the shared templates or process clones
   */
  private array $rootCommitCloneUrls = [];
  // #endregion

  // #region Methods
  /**
   * Method isolateRootCommitDatabases
   *
   * Runs before ordinary setup so every service resolves the per-test URLs on a fresh kernel.
   *
   * @access protected
   *
   * @return void
   */
  #[Before(100)]
  protected function isolateRootCommitDatabases(): void
  {
    if (StaticDriver::isKeepStaticConnections() || !function_exists('fireguard_isolated_database_url')) {
      throw new LogicException('True-commit API tests require SkipDatabaseRollback and the repository test bootstrap.');
    }
    static::ensureKernelShutdown();
    $token = 'rootcommit_' . bin2hex(random_bytes(8));
    /** @var Closure(string, string):string $cloneDatabase */
    $cloneDatabase = Closure::fromCallable('fireguard_isolated_database_url');

    try {
      foreach (['AUTH_DATABASE_URL', 'MAIN_DATABASE_URL'] as $name) {
        $this->rootCommitOriginalUrls[$name] = ['env' => $_ENV[$name] ?? null, 'server' => $_SERVER[$name] ?? null];
        $url = $_ENV[$name] ?? $_SERVER[$name] ?? null;
        if (!is_string($url)) {
          throw new LogicException('The isolated test database URL is unavailable.');
        }
        $clone = $cloneDatabase($url, $token);
        RootCommitDatabaseCleanup::ownedDatabaseName($clone);
        $this->rootCommitCloneUrls[] = $clone;
        $_ENV[$name] = $_SERVER[$name] = $clone;
      }
    } catch (Throwable $failure) {
      try {
        $this->restoreRootCommitDatabases();
      } catch (Throwable) {
        // The bootstrap shutdown cleanup remains a fallback; preserve the setup failure.
      }

      throw $failure;
    }
  }

  /**
   * Method restoreRootCommitDatabases
   *
   * Runs after ordinary teardown and restores the worker's original URLs even if clone cleanup fails.
   *
   * @access protected
   *
   * @return void
   */
  #[After(-100)]
  protected function restoreRootCommitDatabases(): void
  {
    $failure = null;

    try {
      static::ensureKernelShutdown();
      foreach ($this->rootCommitCloneUrls as $clone) {
        try {
          RootCommitDatabaseCleanup::drop($clone);
        } catch (Throwable $cleanupFailure) {
          $failure ??= $cleanupFailure;
        }
      }
    } finally {
      foreach ($this->rootCommitOriginalUrls as $name => $values) {
        if (null === $values['env']) {
          unset($_ENV[$name]);
        } else {
          $_ENV[$name] = $values['env'];
        }
        if (null === $values['server']) {
          unset($_SERVER[$name]);
        } else {
          $_SERVER[$name] = $values['server'];
        }
      }
      $this->rootCommitOriginalUrls = [];
      $this->rootCommitCloneUrls = [];
    }

    if (null !== $failure) {
      throw $failure;
    }
  }
  // #endregion
}
