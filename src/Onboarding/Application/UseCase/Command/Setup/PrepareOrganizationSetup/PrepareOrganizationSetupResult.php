<?php

declare(strict_types=1);

namespace Onboarding\Application\UseCase\Command\Setup\PrepareOrganizationSetup;

use Onboarding\Application\Service\OrganizationOnboardingSessionState;

/**
 * Durable organization setup recovery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PrepareOrganizationSetupResult implements \Shared\Application\Message\ResultMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public OrganizationOnboardingSessionState $state)
  {
  }
}
