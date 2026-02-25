<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Operation;

/**
 * Onboarding operation names.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OnboardingOperations
{
  // #region Constants
  /**
   * Constant GET_ORGANIZATION_ONBOARDING.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string GET_ORGANIZATION_ONBOARDING = 'getOrganizationOnboarding';

  /**
   * Constant START_ORGANIZATION_ONBOARDING.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string START_ORGANIZATION_ONBOARDING = 'startOrganizationOnboarding';

  /**
   * Constant EXECUTE_ORGANIZATION_ONBOARDING_STEP.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string EXECUTE_ORGANIZATION_ONBOARDING_STEP = 'executeOrganizationOnboardingStep';

  /**
   * Constant ROLLBACK_ORGANIZATION_ONBOARDING.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ROLLBACK_ORGANIZATION_ONBOARDING = 'rollbackOrganizationOnboarding';

  /**
   * Constant SKIP_ORGANIZATION_ONBOARDING_STEP.
   *
   * @since 2.0.0
   *
   * @var string
   */
  public const string SKIP_ORGANIZATION_ONBOARDING_STEP = 'skipOrganizationOnboardingStep';
  // #endregion
}
