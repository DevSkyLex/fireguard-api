<?php

declare(strict_types=1);

namespace Onboarding\Application\UseCase\Command\Flow\RollbackOrganizationOnboarding;

use InvalidArgumentException;
use Onboarding\Application\Service\OrganizationOnboardingSessionState;
use Shared\Application\Message\ResultMessage;

/**
 * Owns the durable organization onboarding HTTP transition.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RollbackOrganizationOnboardingResult implements ResultMessage
{
  public ?string $organizationId;

  public function __construct(public OrganizationOnboardingSessionState $state, ?string $organizationId = null)
  {
    $this->organizationId = $state->targetOrganizationId ?? $state->accessibleOrganizationId;
    if (null !== $organizationId && $organizationId !== $this->organizationId) {
      throw new InvalidArgumentException('Onboarding result organization does not match its state.');
    }
  }
}
