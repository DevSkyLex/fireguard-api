<?php

declare(strict_types=1);

namespace Onboarding\Application\UseCase\Command\Setup\PrepareOrganizationSetup;

use InvalidArgumentException;
use Onboarding\Application\Service\OrganizationOnboardingSessionState;

/**
 * Durable organization setup recovery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PrepareOrganizationSetupResult implements \Shared\Application\Message\ResultMessage
{
  /**
   * @since 1.0.0
   */
  public ?string $organizationId;

  public function __construct(public OrganizationOnboardingSessionState $state, ?string $organizationId = null)
  {
    $this->organizationId = $state->targetOrganizationId ?? $state->accessibleOrganizationId;
    if (null !== $organizationId && $organizationId !== $this->organizationId) {
      throw new InvalidArgumentException('Onboarding result organization does not match its state.');
    }
  }
}
