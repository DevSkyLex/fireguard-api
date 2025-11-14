<?php

declare(strict_types=1);

namespace Shared\Domain\Exception;

use Shared\Domain\Exception\DomainException;

/**
 * Exception InvalidValueException
 * @final
 *
 * It is thrown when an invalid value is provided.
 *
 * @category Exception
 * @package Shared\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidValueException extends DomainException
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
