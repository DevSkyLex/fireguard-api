<?php

declare(strict_types=1);

namespace Shared\Domain\Exception;

use function sprintf;

/**
 * Exception InvalidValueException
 *
 * It is thrown when an invalid value is provided.
 *
 * @category Exception
 * @package Shared\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class InvalidValueException extends DomainException
{
  //#region Methods
  /**
   * Method because
   *
   * Creates a new InvalidValueException
   * with the specified message.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The message to be displayed.
   *
   * @return self The created InvalidValueException.
   */
  public static function because(string $message): self
  {
    return new self($message);
  }
  //#endregion
}
