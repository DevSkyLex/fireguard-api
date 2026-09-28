<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\ReadMarker\MarkConversationRead;

use DateTimeImmutable;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMessageRepositoryPort, MessagingReadMarkerRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;

use function str_contains;
use function strrchr;
use function substr;

/**
 * UseCase MarkConversationReadHandler.
 *
 * Records that the acting member has read a conversation up to now (and,
 * when provided, up to a specific message), used to compute unread counts
 * on `ListConversations`/`ListChannels`. Marking a channel read is
 * participant-gated (same rule as reading it); marking a subject thread
 * read requires the subject's own read permission. The owning organization
 * is derived from the loaded conversation, not supplied by the caller.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MarkConversationReadHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingReadMarkerRepositoryPort $readMarkers the read marker repository port
   * @param MessagingSubjectResolverRegistry $resolvers the subject resolver registry
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingReadMarkerRepositoryPort $readMarkers,
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
    private MessagingMessageRepositoryPort $messages,
    private MessagingRealtimePublisherPort $realtime,
    private LoggerPort $logger,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param MarkConversationReadCommand $command the command value
   *
   * @return MarkConversationReadResult the use case result
   */
  public function __invoke(MarkConversationReadCommand $command): MarkConversationReadResult
  {
    $conversation = $this->conversations->findById($command->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }

    $organizationId = $conversation->organizationId;
    $memberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);

    if (ConversationVisibility::PARTICIPANTS->value === $conversation->visibility) {
      $this->accessPolicy->assertCanReadChannel($command->userId, $organizationId, $conversation->id, $memberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.read';
      if (null !== $conversation->subjectId) {
        $subjectType = MessagingSubjectType::from($conversation->subjectType);
        $resolution = $this->resolvers->resolve($subjectType, $organizationId, $conversation->subjectId);
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }
      $this->accessPolicy->assertCanReadThread($command->userId, $organizationId, $requiredSubjectPermission);
    }

    $lastReadMessageId = $command->lastReadMessageId;
    if (null !== $lastReadMessageId) {
      $lastReadMessageId = str_contains($lastReadMessageId, '/') ? substr((string) strrchr($lastReadMessageId, '/'), 1) : $lastReadMessageId;
      $message = $this->messages->findById($lastReadMessageId);
      if (null === $message || $message->conversationId !== $conversation->id || $message->organizationId !== $organizationId) {
        throw MessagingNotFoundException::message($lastReadMessageId);
      }
    }

    $this->readMarkers->upsert(
      $command->conversationId,
      $organizationId,
      $memberId,
      new DateTimeImmutable(),
      $lastReadMessageId,
    );

    if (null !== $lastReadMessageId && ConversationVisibility::PARTICIPANTS->value === $conversation->visibility) {
      try {
        $this->realtime->publishMessage($organizationId, $conversation->id, ['type' => 'receipt.changed', 'memberId' => $memberId]);
      } catch (Throwable $exception) {
        $this->logger->warning('Messaging read receipt realtime publish failed.', ['conversationId' => $conversation->id, 'error' => $exception->getMessage()]);
      }
    }

    return new MarkConversationReadResult($conversation);
  }
}
