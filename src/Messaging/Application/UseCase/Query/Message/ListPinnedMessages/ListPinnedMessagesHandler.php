<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Message\ListPinnedMessages;

use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMessageRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListPinnedMessagesHandler.
 *
 * Gates the Pins tab by the exact same access rule as a normal message read
 * — mirrors `ListMessagesHandler`/`ListConversationAttachmentsHandler` byte
 * for byte — via `MessagingAccessPolicy`, so there is a single access path
 * for reading a conversation's content. A pin is a property of the
 * CONVERSATION: every member who can read the conversation sees the same
 * pinned set, unlike a saved message (private to one member).
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListPinnedMessagesHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingSubjectResolverRegistry $resolvers the subject resolver registry
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingMessageRepositoryPort $messages,
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.1.0
   *
   * @param ListPinnedMessagesQuery $query the query value
   *
   * @return ListPinnedMessagesResult the use case result
   */
  public function __invoke(ListPinnedMessagesQuery $query): ListPinnedMessagesResult
  {
    $conversation = $this->conversations->findById($query->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($query->conversationId);
    }

    $memberId = $this->accessPolicy->resolveActiveMemberId($conversation->organizationId, $query->userId);

    if (ConversationVisibility::PARTICIPANTS->value === $conversation->visibility) {
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

    return new ListPinnedMessagesResult(
      $this->messages->listPinnedByConversation($query->conversationId, $query->page, $query->itemsPerPage),
      $memberId,
    );
  }
}
