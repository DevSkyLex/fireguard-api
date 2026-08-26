<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

/**
 * Exception PlanNotAvailableException.
 *
 * Raised when the selected plan exists but is not active, so it cannot be
 * subscribed to. Distinct from `PlanNotFoundException`, which means no such
 * plan at all.
 *
 * Mapped to 400, and deliberately NOT moved to 409 with the conflict family
 * arbitrated on 2026-08-26: nothing about the organization's state forbids
 * subscribing, the caller simply named a value that is not one of the
 * available plans.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanNotAvailableException extends RuntimeException
{
  // #region Methods
  /**
   * Method create.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function create(): self
  {
    return new self('The selected plan is not available.');
  }
  // #endregion
}
