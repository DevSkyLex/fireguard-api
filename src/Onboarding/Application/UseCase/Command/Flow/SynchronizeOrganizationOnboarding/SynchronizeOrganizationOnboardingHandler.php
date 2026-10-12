<?php

declare(strict_types=1);

namespace Onboarding\Application\UseCase\Command\Flow\SynchronizeOrganizationOnboarding;

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
final readonly class SynchronizeOrganizationOnboardingHandler implements CommandHandler
{
  public function __construct(private OrganizationOnboardingServicePort $flow)
  {
  }

  public function __invoke(SynchronizeOrganizationOnboardingCommand $command): SynchronizeOrganizationOnboardingResult
  {
    return new SynchronizeOrganizationOnboardingResult($this->flow->getFlow($command->userId));
  }
}
