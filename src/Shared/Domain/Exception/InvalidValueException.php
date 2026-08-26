<?php

declare(strict_types=1);

namespace Shared\Domain\Exception;

use Throwable;

/**
 * Exception InvalidValueException.
 *
 * It is thrown when an invalid value is provided.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class InvalidValueException extends DomainException
{
  // #region Methods
  /**
   * Method because.
   *
   * Creates a new InvalidValueException
   * with the specified message.
   *
   * @since 1.0.0
   *
   * @param string $message the message to be displayed
   * @param ?Throwable $previous the cause, when this wraps a lower-level failure
   *                             such as a `ValueError` from a backed enum — the
   *                             chain is what tells a reader WHICH enum rejected
   *                             the value, so do not drop it when one exists
   *
   * @return self the created InvalidValueException
   */
  public static function because(string $message, ?Throwable $previous = null): self
  {
    return new self($message, 0, $previous);
  }
  // #endregion
}
