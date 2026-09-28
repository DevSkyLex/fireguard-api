<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\ReadMarker\AcknowledgeDelivery;

use DateTimeImmutable;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMessageRepositoryPort, MessagingReadMarkerRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\ValueObject\ConversationVisibility;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;

/** Persists a delivery acknowledgement after checking participant and message ownership. */
final readonly class AcknowledgeDeliveryHandler implements CommandHandler
{
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingMessageRepositoryPort $messages,
    private MessagingReadMarkerRepositoryPort $readMarkers,
    private MessagingRealtimePublisherPort $realtime,
    private MessagingAccessPolicy $accessPolicy,
    private LoggerPort $logger,
  ) {
  }

  public function __invoke(AcknowledgeDeliveryCommand $command): AcknowledgeDeliveryResult
  {
    $conversation = $this->conversations->findById($command->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }

    $memberId = $this->accessPolicy->resolveActiveMemberId($conversation->organizationId, $command->userId);
    if (ConversationVisibility::PARTICIPANTS->value !== $conversation->visibility) {
      throw new MessagingValidationException('Delivery receipts require a participant conversation.');
    }
    $this->accessPolicy->assertCanReadChannel($command->userId, $conversation->organizationId, $conversation->id, $memberId);

    $message = $this->messages->findById($command->messageId);
    if (null === $message || $message->conversationId !== $conversation->id || $message->organizationId !== $conversation->organizationId) {
      throw MessagingNotFoundException::message($command->messageId);
    }
    if ($message->authorMemberId === $memberId) {
      throw new MessagingValidationException('A member cannot acknowledge their own message.');
    }

    $this->readMarkers->markDelivered($conversation->id, $conversation->organizationId, $memberId, $message->id, new DateTimeImmutable());

    try {
      $this->realtime->publishMessage($conversation->organizationId, $conversation->id, ['type' => 'receipt.changed', 'memberId' => $memberId]);
    } catch (Throwable $exception) {
      $this->logger->warning('Messaging receipt realtime publish failed.', ['conversationId' => $conversation->id, 'error' => $exception->getMessage()]);
    }

    return new AcknowledgeDeliveryResult($message->id);
  }
}
