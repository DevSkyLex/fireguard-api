<?php

declare(strict_types=1);

namespace Onboarding\Application\UseCase\Command\Flow\RollbackOrganizationOnboarding;

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
final readonly class RollbackOrganizationOnboardingHandler implements CommandHandler
{
  public function __construct(private OrganizationOnboardingServicePort $flow)
  {
  }

  public function __invoke(RollbackOrganizationOnboardingCommand $command): RollbackOrganizationOnboardingResult
  {
    return new RollbackOrganizationOnboardingResult($this->flow->rollbackLastStep($command->userId));
  }
}
