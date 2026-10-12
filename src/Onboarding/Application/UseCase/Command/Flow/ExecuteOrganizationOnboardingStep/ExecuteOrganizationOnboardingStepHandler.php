<?php

declare(strict_types=1);

namespace Onboarding\Application\UseCase\Command\Flow\ExecuteOrganizationOnboardingStep;

use Onboarding\Application\Port\Inbound\Internal\OrganizationOnboardingServicePort;
use Onboarding\Application\Service\ExecuteOnboardingStepPayload;
use Shared\Application\Message\CommandHandler;

/**
 * Owns the durable organization onboarding HTTP transition.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExecuteOrganizationOnboardingStepHandler implements CommandHandler
{
  public function __construct(private OrganizationOnboardingServicePort $flow)
  {
  }

  public function __invoke(ExecuteOrganizationOnboardingStepCommand $command): ExecuteOrganizationOnboardingStepResult
  {
    return new ExecuteOrganizationOnboardingStepResult($this->flow->executeStep($command->userId, $command->stepKey, new ExecuteOnboardingStepPayload()));
  }
}
