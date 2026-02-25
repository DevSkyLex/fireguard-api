<?php

declare(strict_types=1);

namespace Onboarding\Application\Port\Inbound;

use Onboarding\Application\Service\{ExecuteOnboardingStepPayload, OrganizationOnboardingSessionState};

/**
 * Port OrganizationOnboardingServicePort.
 *
 * Inbound application-layer contract for the organization onboarding flow.
 * Presentation-layer components (providers, processors) depend on this
 * abstraction rather than on the concrete service, enabling clean substitution
 * and simplified testing.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationOnboardingServicePort
{
  /**
   * Method getFlow.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   *
   * @return OrganizationOnboardingSessionState the current flow state
   */
  public function getFlow(string $userId): OrganizationOnboardingSessionState;

  /**
   * Method start.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   * @param bool $reset whether to delete and recreate the persisted session
   *
   * @return OrganizationOnboardingSessionState the current flow state after start
   */
  public function start(string $userId, bool $reset = false): OrganizationOnboardingSessionState;

  /**
   * Method executeStep.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   * @param string $stepKey the step to execute
   * @param ExecuteOnboardingStepPayload $input the step input payload
   *
   * @return OrganizationOnboardingSessionState the updated flow state
   */
  public function executeStep(string $userId, string $stepKey, ExecuteOnboardingStepPayload $input): OrganizationOnboardingSessionState;

  /**
   * Method skipStep.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   * @param string $stepKey the step to skip (only non-required steps are skippable)
   *
   * @return OrganizationOnboardingSessionState the updated flow state
   */
  public function skipStep(string $userId, string $stepKey): OrganizationOnboardingSessionState;

  /**
   * Method rollbackLastStep.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   *
   * @return OrganizationOnboardingSessionState the updated flow state after rollback
   */
  public function rollbackLastStep(string $userId): OrganizationOnboardingSessionState;
}
