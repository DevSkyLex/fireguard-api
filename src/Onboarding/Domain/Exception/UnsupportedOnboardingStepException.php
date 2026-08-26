<?php

declare(strict_types=1);

namespace Onboarding\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception UnsupportedOnboardingStepException.
 *
 * Raised when a caller names a step that is not in
 * `OrganizationOnboardingStep`. Replaces the `InvalidArgumentException` the
 * flow service used to throw: the status is now declared once in
 * `api_platform.exception_to_status` instead of being decided by whichever
 * processor happened to catch it.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UnsupportedOnboardingStepException extends RuntimeException
{
  // #region Methods
  /**
   * Method withStepKey.
   *
   * Creates an exception for an onboarding step key the catalog does not know.
   *
   * @since 1.0.0
   *
   * @param string $stepKey the step key the caller supplied
   *
   * @return self the exception instance
   */
  public static function withStepKey(string $stepKey): self
  {
    return new self(sprintf('Unsupported onboarding step "%s".', $stepKey));
  }
  // #endregion
}
