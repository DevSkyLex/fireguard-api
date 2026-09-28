<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\Conversation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Messaging\Application\UseCase\Command\Conversation\PublishTyping\PublishTypingCommand;
use Messaging\Presentation\Api\Dto\Input\PublishTypingInput;
use Messaging\Presentation\Api\Dto\Output\ConversationSignalOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function is_string;
use function max;
use function time;

/**
 * Authorizes and rate limits one short-lived typing signal.
 *
 * @implements ProcessorInterface<mixed, ConversationSignalOutput>
 */
final readonly class PublishTypingProcessor implements ProcessorInterface
{
  public function __construct(
    private CommandBusPort $commandBus,
    private CurrentActorPort $actor,
    #[Autowire(service: 'limiter.messaging_typing')]
    private RateLimiterFactory $rateLimiter,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConversationSignalOutput
  {
    $userId = $this->actor->userId();
    if (null === $userId) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id) || '' === $id || !$data instanceof PublishTypingInput) {
      throw new BadRequestHttpException('A conversation id and typing state are required.');
    }

    $limit = $this->rateLimiter->create($userId)->consume();
    if (!$limit->isAccepted()) {
      throw new TooManyRequestsHttpException(max(0, $limit->getRetryAfter()->getTimestamp() - time()), 'Too many typing signals.');
    }

    $this->commandBus->dispatch(new PublishTypingCommand($userId, $id, $data->active));

    return new ConversationSignalOutput();
  }
}
