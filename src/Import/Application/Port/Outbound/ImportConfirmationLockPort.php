<?php

declare(strict_types=1);

namespace Import\Application\Port\Outbound;

/** Port ImportConfirmationLockPort. Serializes confirmation and commits the real job and queue together. */
interface ImportConfirmationLockPort
{
  /**
   * @template T
   *
   * @param callable():T $operation
   *
   * @return T
   */
  public function synchronized(string $simulationId, callable $operation): mixed;
}
