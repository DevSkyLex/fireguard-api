<?php

declare(strict_types=1);

namespace Shared\Application\Exception;

use RuntimeException;

/**
 * Exception ApplicationException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
abstract class ApplicationException extends RuntimeException
{
  // #region Methods
  /**
   * Method context.
   *
   * Returns the context of
   * the exception.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed> the context of the exception
   */
  public function context(): array
  {
    return [];
  }
  // #endregion
}
