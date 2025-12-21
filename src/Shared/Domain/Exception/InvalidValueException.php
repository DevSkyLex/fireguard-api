<?php

declare(strict_types=1);

namespace Shared\Domain\Exception;

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
   *
   * @return self the created InvalidValueException
   */
  public static function because(string $message): self
  {
    return new self($message);
  }
  // #endregion
}
