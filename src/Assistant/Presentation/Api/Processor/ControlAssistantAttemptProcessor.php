<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Assistant\Application\UseCase\Command\Message\ControlAssistantAttempt\{ControlAssistantAttemptCommand, ControlAssistantAttemptResult};
use Assistant\Presentation\Api\Dto\Input\ControlAssistantAttemptInput;
use Assistant\Presentation\Api\Dto\Output\AssistantMessageOutput;
use Assistant\Presentation\Api\Factory\AssistantMessageOutputFactory;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/** @implements ProcessorInterface<ControlAssistantAttemptInput, AssistantMessageOutput> */
final readonly class ControlAssistantAttemptProcessor implements ProcessorInterface
{
  public function __construct(private CommandBusPort $commands, private CurrentActorPort $actor, private AssistantMessageOutputFactory $factory)
  {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AssistantMessageOutput
  {
    $actor = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organization = $uriVariables['organizationId'] ?? null;
    $thread = $uriVariables['threadId'] ?? null;
    $message = $uriVariables['messageId'] ?? null;
    if (!is_string($organization) || !is_string($thread) || !is_string($message)) {
      throw new BadRequestHttpException('Organization, thread, message and attempt identifiers are required.');
    }

    /** @var ControlAssistantAttemptResult $result */
    $result = $this->commands->dispatch(new ControlAssistantAttemptCommand($actor, $organization, $thread, $message, $data->attemptId, 'assistant_retry_generation' === $operation->getName()));

    return $this->factory->fromView($result->message);
  }
}
