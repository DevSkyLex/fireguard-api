<?php

declare(strict_types=1);

namespace Tests\Support\Assistant;

use Assistant\Application\Port\Outbound\AssistantAttemptLockPort;

final readonly class ImmediateAttemptLock implements AssistantAttemptLockPort
{
  public function synchronized(string $messageId, callable $operation): mixed
  {
    return $operation();
  }
}
