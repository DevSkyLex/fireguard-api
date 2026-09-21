<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/** Port IdempotentConsumerPort. Receipts commit with the consumer's local writes. */
interface IdempotentConsumerPort
{
  /**
   * @param callable():void $operation local work, using the same database as this consumer
   *
   * @return bool false when this consumer already committed this event
   */
  public function consume(string $eventId, string $consumer, callable $operation): bool;
}
