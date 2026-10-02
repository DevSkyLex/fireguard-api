<?php

declare(strict_types=1);

namespace Assistant\Domain\Exception;

use RuntimeException;

/**
 * Class AssistantQuestionUnavailableException
 *
 * Prevents generation when its completed user-question anchor cannot be recovered from the owning thread.
 *
 * @category Exception
 */
final class AssistantQuestionUnavailableException extends RuntimeException
{
  // #region Methods
  /**
   * Method unavailable
   *
   * Reports an unusable generation anchor without exposing question content or identifiers.
   *
   * @access public
   *
   * @return self the exception consumed by the worker's existing generation-failure handling
   */
  public static function unavailable(): self
  {
    return new self('Assistant generation question is unavailable.');
  }
  // #endregion
}
