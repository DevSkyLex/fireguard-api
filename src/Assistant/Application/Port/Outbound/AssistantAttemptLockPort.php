<?php

declare(strict_types=1);

namespace Assistant\Application\Port\Outbound;

/** Port AssistantAttemptLockPort. Serializes generation, cancellation and retry in main. */
interface AssistantAttemptLockPort
{
  /**
   * @template T
   *
   * @param callable():T $operation
   *
   * @return T
   */
  public function synchronized(string $messageId, callable $operation): mixed;
}
