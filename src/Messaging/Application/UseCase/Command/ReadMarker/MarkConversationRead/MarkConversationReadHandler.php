<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\ReadMarker\MarkConversationRead;

use DateTimeImmutable;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Shared\Application\Message\CommandHandler;

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

    $this->readMarkers->upsert(
      $command->conversationId,
      $organizationId,
      $memberId,
      new DateTimeImmutable(),
      $command->lastReadMessageId,
    );

    return new MarkConversationReadResult($conversation);
  }
}
