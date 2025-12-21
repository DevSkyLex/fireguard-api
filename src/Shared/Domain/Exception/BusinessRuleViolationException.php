<?php

declare(strict_types=1);

namespace Shared\Domain\Exception;

use function sprintf;

/**
 * Exception BusinessRuleViolationException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class BusinessRuleViolationException extends DomainException
{
  // #region Methods
  /**
   * Method because.
   *
   * Creates an exception with a specific rule violation message.
   *
   * @since 1.0.0
   *
   * @param string $rule the business rule that was violated
   *
   * @return self the exception instance
   */
  public static function because(string $rule): self
  {
    return new self(
      message: sprintf('Business rule violated: %s', $rule)
    );
  }
  // #endregion
}
