<?php

declare(strict_types=1);

namespace Assistant\Domain\Exception;

use RuntimeException;

/** Exception AssistantAttemptConflictException. The requested attempt no longer permits this action. */
final class AssistantAttemptConflictException extends RuntimeException
{
  public static function stale(): self
  {
    return new self('This generation attempt has changed. Refresh the conversation.');
  }

  public static function notRetryable(): self
  {
    return new self('This reply cannot be retried.');
  }
}
