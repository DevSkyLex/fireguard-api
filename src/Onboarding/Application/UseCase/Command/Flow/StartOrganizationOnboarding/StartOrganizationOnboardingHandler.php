<?php

declare(strict_types=1);

namespace Onboarding\Application\UseCase\Command\Flow\StartOrganizationOnboarding;

use Onboarding\Application\Port\Inbound\Internal\OrganizationOnboardingServicePort;
use Shared\Application\Message\CommandHandler;

/**
 * Owns the durable organization onboarding HTTP transition.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartOrganizationOnboardingHandler implements CommandHandler
{
  public function __construct(private OrganizationOnboardingServicePort $flow)
  {
  }

  public function __invoke(StartOrganizationOnboardingCommand $command): StartOrganizationOnboardingResult
  {
    return new StartOrganizationOnboardingResult($this->flow->start($command->userId, $command->reset, $command->intent));
  }
}
