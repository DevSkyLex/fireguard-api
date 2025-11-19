<?php

declare(strict_types=1);

namespace Shared\Domain\Exception;

/**
 * Exception BusinessRuleViolationException
 * @final
 *
 * Thrown when a business rule is violated.
 *
 * @category Exception
 * @package Shared\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class BusinessRuleViolationException extends DomainException
{
  //#region Methods
  /**
   * Method because
   *
   * Creates an exception with a specific rule violation message.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $rule The business rule that was violated.
   *
   * @return self The exception instance.
   */
  public static function because(string $rule): self
  {
    return new self(
      message: sprintf('Business rule violated: %s', $rule)
    );
  }
  //#endregion
}
