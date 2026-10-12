<?php

declare(strict_types=1);

namespace Onboarding\Application\UseCase\Command\Flow\SkipOrganizationOnboardingStep;

use Shared\Application\Message\CommandMessage;

/**
 * Owns the durable organization onboarding HTTP transition.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SkipOrganizationOnboardingStepCommand implements CommandMessage
{
  public function __construct(public string $userId, public string $stepKey)
  {
  }
}
