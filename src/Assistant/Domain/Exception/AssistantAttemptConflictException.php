<?php

declare(strict_types=1);

namespace Assistant\Domain\Exception;

use RuntimeException;

/** Exception AssistantAttemptConflictException. The requested attempt no longer permits this action. */
final class AssistantAttemptConflictException extends RuntimeException
{
  // #region Methods
  /**
   * Method stale
   *
   * Creates a conflict exception when the generation attempt changed before the requested action could be applied.
   *
   * @access public
   *
   * @return self the created instance
   */
  public static function stale(): self
  {
    return new self('This generation attempt has changed. Refresh the conversation.');
  }

  /**
   * Method notRetryable
   *
   * Creates a conflict exception when the current reply cannot be retried.
   *
   * @access public
   *
   * @return self the created instance
   */
  public static function notRetryable(): self
  {
    return new self('This reply cannot be retried.');
  }
  // #endregion
}
