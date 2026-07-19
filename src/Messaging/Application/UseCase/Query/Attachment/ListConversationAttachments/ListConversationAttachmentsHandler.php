<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Attachment\ListConversationAttachments;

use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingConversationRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListConversationAttachmentsHandler.
 *
 * Gates the Files tab by the exact same access rule as a normal message
 * read — mirrors `ListMessagesHandler` byte for byte — via
 * `MessagingAccessPolicy`, so there is a single access path for reading a
 * conversation's content.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListConversationAttachmentsHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingAttachmentRepositoryPort $attachments,
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(ListConversationAttachmentsQuery $query): ListConversationAttachmentsResult
  {
    $conversation = $this->conversations->findById($query->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($query->conversationId);
    }

    if (ConversationVisibility::PARTICIPANTS->value === $conversation->visibility) {
      $memberId = $this->accessPolicy->resolveActiveMemberId($conversation->organizationId, $query->userId);
      $this->accessPolicy->assertCanReadChannel($query->userId, $conversation->organizationId, $conversation->id, $memberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.read';
      if (null !== $conversation->subjectId) {
        $subjectType = MessagingSubjectType::from($conversation->subjectType);
        $resolution = $this->resolvers->resolve($subjectType, $conversation->organizationId, $conversation->subjectId);
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }
      $this->accessPolicy->assertCanReadThread($query->userId, $conversation->organizationId, $requiredSubjectPermission);
    }

    return new ListConversationAttachmentsResult(
      $this->attachments->listByConversationId($query->conversationId, $query->page, $query->itemsPerPage),
    );
  }
  // #endregion
}
