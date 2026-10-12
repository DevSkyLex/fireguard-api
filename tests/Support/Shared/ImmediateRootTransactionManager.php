<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Shared\Application\Port\Outbound\RootTransactionManagerPort;

/**
 * Unit-test transaction owner; PostgreSQL integration tests use the real owning adapter.
 *
 * @category Test Support
 */
final readonly class ImmediateRootTransactionManager implements RootTransactionManagerPort
{
  /**
   * {@inheritDoc}
   */
  public function assertCanStart(): void
  {
  }

  /**
   * {@inheritDoc}
   */
  public function transactional(callable $operation): mixed
  {
    return $operation();
  }
}
