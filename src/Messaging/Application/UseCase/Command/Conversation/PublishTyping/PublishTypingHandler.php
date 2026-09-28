<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\PublishTyping;

use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\ValueObject\ConversationVisibility;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;

/** Publishes a short-lived typing signal to the private conversation topic. */
final readonly class PublishTypingHandler implements CommandHandler
{
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingRealtimePublisherPort $realtime,
    private MessagingAccessPolicy $accessPolicy,
    private LoggerPort $logger,
  ) {
  }

  public function __invoke(PublishTypingCommand $command): PublishTypingResult
  {
    $conversation = $this->conversations->findById($command->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }
    $memberId = $this->accessPolicy->resolveActiveMemberId($conversation->organizationId, $command->userId);
    if (ConversationVisibility::PARTICIPANTS->value !== $conversation->visibility) {
      throw new MessagingValidationException('Typing signals require a participant conversation.');
    }
    $this->accessPolicy->assertCanWriteChannel($command->userId, $conversation->organizationId, $conversation->id, $memberId);
    if ($conversation->isArchived) {
      throw new MessagingValidationException('Cannot type in an archived conversation.');
    }

    try {
      $this->realtime->publishMessage($conversation->organizationId, $conversation->id, ['type' => 'typing.changed', 'memberId' => $memberId, 'active' => $command->active]);
    } catch (Throwable $exception) {
      $this->logger->warning('Messaging typing realtime publish failed.', ['conversationId' => $conversation->id, 'error' => $exception->getMessage()]);
    }

    return new PublishTypingResult($memberId);
  }
}
