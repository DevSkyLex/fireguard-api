<?php

declare(strict_types=1);

namespace Tests\Helper\Onboarding;

use LogicException;
use Onboarding\Application\Port\Inbound\Internal\OrganizationOnboardingServicePort;
use Onboarding\Application\UseCase\Command\Flow\DismissOrganizationOnboarding\{DismissOrganizationOnboardingCommand, DismissOrganizationOnboardingHandler};
use Onboarding\Application\UseCase\Command\Flow\ExecuteOrganizationOnboardingStep\{ExecuteOrganizationOnboardingStepCommand, ExecuteOrganizationOnboardingStepHandler};
use Onboarding\Application\UseCase\Command\Flow\ResumeOrganizationOnboarding\{ResumeOrganizationOnboardingCommand, ResumeOrganizationOnboardingHandler};
use Onboarding\Application\UseCase\Command\Flow\RollbackOrganizationOnboarding\{RollbackOrganizationOnboardingCommand, RollbackOrganizationOnboardingHandler};
use Onboarding\Application\UseCase\Command\Flow\SkipOrganizationOnboardingStep\{SkipOrganizationOnboardingStepCommand, SkipOrganizationOnboardingStepHandler};
use Onboarding\Application\UseCase\Command\Flow\StartOrganizationOnboarding\{StartOrganizationOnboardingCommand, StartOrganizationOnboardingHandler};
use Onboarding\Application\UseCase\Command\Flow\SynchronizeOrganizationOnboarding\{SynchronizeOrganizationOnboardingCommand, SynchronizeOrganizationOnboardingHandler};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Keeps existing flow fixtures behind the same typed bus boundary as HTTP.
 *
 * @category Test Helper
 */
final readonly class OrganizationOnboardingCommandBus implements CommandBusPort
{
  public function __construct(private OrganizationOnboardingServicePort $flow)
  {
  }

  public function dispatch(CommandMessage $command): ResultMessage
  {
    return match (true) {
      $command instanceof SynchronizeOrganizationOnboardingCommand => new SynchronizeOrganizationOnboardingHandler($this->flow)($command),
      $command instanceof StartOrganizationOnboardingCommand => new StartOrganizationOnboardingHandler($this->flow)($command),
      $command instanceof ExecuteOrganizationOnboardingStepCommand => new ExecuteOrganizationOnboardingStepHandler($this->flow)($command),
      $command instanceof SkipOrganizationOnboardingStepCommand => new SkipOrganizationOnboardingStepHandler($this->flow)($command),
      $command instanceof RollbackOrganizationOnboardingCommand => new RollbackOrganizationOnboardingHandler($this->flow)($command),
      $command instanceof DismissOrganizationOnboardingCommand => new DismissOrganizationOnboardingHandler($this->flow)($command),
      $command instanceof ResumeOrganizationOnboardingCommand => new ResumeOrganizationOnboardingHandler($this->flow)($command),
      default => throw new LogicException('Unsupported onboarding test command.'),
    };
  }
}
