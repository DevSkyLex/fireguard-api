<?php

declare(strict_types=1);

namespace Onboarding\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception OnboardingStepNotExecutableException.
 *
 * Raised when a step is known but cannot run yet: the session has no target
 * organization, or the action the step confirms has not happened.
 *
 * Both answer 400, which is what the `InvalidArgumentException` this replaces
 * already answered. A case can be made that "the required action has not been
 * completed" is a state conflict (409) rather than a malformed request — that
 * would be a CONTRACT CHANGE and is deliberately not made here. A status is a
 * published contract, and changing one belongs in its own decision.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OnboardingStepNotExecutableException extends RuntimeException
{
  // #region Methods
  /**
   * Method noTargetOrganization.
   *
   * Creates an exception for a session that has not created its organization.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function noTargetOrganization(): self
  {
    return new self('No organization found. Create an organization first via POST /api/organizations.');
  }

  /**
   * Method noTargetOrganizationForStep.
   *
   * Creates an exception for a step that needs the organization the session
   * never created.
   *
   * @since 1.0.0
   *
   * @param string $stepKey the step that cannot run
   *
   * @return self the exception instance
   */
  public static function noTargetOrganizationForStep(string $stepKey): self
  {
    return new self(sprintf('No organization found. Cannot execute step "%s".', $stepKey));
  }

  /**
   * Method requiredActionNotCompleted.
   *
   * Creates an exception for a confirmation step whose underlying action has
   * not been performed yet.
   *
   * @since 1.0.0
   *
   * @param string $stepKey the step being confirmed
   *
   * @return self the exception instance
   */
  public static function requiredActionNotCompleted(string $stepKey): self
  {
    return new self(
      sprintf('Cannot confirm step "%s": the required action has not been completed yet.', $stepKey),
    );
  }
  // #endregion
}
