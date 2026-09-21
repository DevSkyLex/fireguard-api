<?php

declare(strict_types=1);

namespace Automation\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Automation\Application\UseCase\Command\RetryAutomationAttempt\{RetryAutomationAttemptCommand, RetryAutomationAttemptResult};
use Automation\Presentation\Api\Dto\Input\RetryAutomationAttemptInput;
use Automation\Presentation\Api\Dto\Output\AutomationAttemptOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/** @implements ProcessorInterface<RetryAutomationAttemptInput, AutomationAttemptOutput> */
final readonly class RetryAutomationAttemptProcessor implements ProcessorInterface
{
  public function __construct(private CommandBusPort $commands, private CurrentActorPort $actor)
  {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AutomationAttemptOutput
  {
    $actor = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organization = $uriVariables['organizationId'] ?? null;
    $run = $uriVariables['runId'] ?? null;
    if (!is_string($organization) || !is_string($run)) {
      throw new BadRequestHttpException('Organization and run are required.');
    }
    /** @var RetryAutomationAttemptResult $result */
    $result = $this->commands->dispatch(new RetryAutomationAttemptCommand($actor, $organization, $run, $data->attemptId));

    return AutomationAttemptOutput::fromView($result->attempt);
  }
}
