<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception PlanNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for a missing plan identifier.
   *
   * @since 1.0.0
   *
   * @param string $id the plan identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Plan with ID "%s" not found.', $id));
  }

  /**
   * Method withKey.
   *
   * Creates an exception for a missing plan key.
   *
   * @since 1.0.0
   *
   * @param string $key the plan key
   *
   * @return self the exception instance
   */
  public static function withKey(string $key): self
  {
    return new self(sprintf('Plan with key "%s" not found.', $key));
  }

  /**
   * Method default.
   *
   * Creates an exception for a missing default plan.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function default(): self
  {
    return new self('No default plan is configured.');
  }
  // #endregion
}
