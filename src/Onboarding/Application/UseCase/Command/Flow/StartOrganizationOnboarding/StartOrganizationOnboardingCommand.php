<?php

declare(strict_types=1);

namespace Onboarding\Application\UseCase\Command\Flow\StartOrganizationOnboarding;

use Shared\Application\Message\CommandMessage;

/**
 * Owns the durable organization onboarding HTTP transition.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartOrganizationOnboardingCommand implements CommandMessage
{
  public function __construct(public string $userId, public bool $reset = false, public ?string $intent = null)
  {
  }
}
