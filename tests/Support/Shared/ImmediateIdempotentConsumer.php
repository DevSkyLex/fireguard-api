<?php

declare(strict_types=1);

namespace App\Tests\Support\Shared;

use Shared\Application\Port\Outbound\IdempotentConsumerPort;

final class ImmediateIdempotentConsumer implements IdempotentConsumerPort
{
  /**
   * @var array<string, true>
   */
  private array $receipts = [];

  public function consume(string $eventId, string $consumer, callable $operation): bool
  {
    $key = $eventId . ':' . $consumer;
    if (isset($this->receipts[$key])) {
      return false;
    }
    $operation();
    $this->receipts[$key] = true;

    return true;
  }
}
