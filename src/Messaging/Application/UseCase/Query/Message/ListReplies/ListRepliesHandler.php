<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Message\ListReplies;

use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMessageRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListRepliesHandler.
 *
 * Gates a message's Thread panel by the EXACT SAME access rule as reading
 * the conversation itself — mirrors `ListMessagesHandler`/
 * `ListPinnedMessagesHandler` byte for byte — via `MessagingAccessPolicy`, so
 * there is a single access path for reading a conversation's content.
 * Deliberately does NOT re-check the parent message's tombstone state: an
 * existing reply remains readable even after its parent is later deleted
 * (only POSTING a NEW reply to a tombstoned parent is refused, by
 * `PostReplyHandler`).
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListRepliesHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * @since 1.2.0
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
   * @since 1.2.0
   *
   * @param ListRepliesQuery $query the query value
   *
   * @return ListRepliesResult the use case result
   */
  public function __invoke(ListRepliesQuery $query): ListRepliesResult
  {
    $parent = $this->messages->findById($query->parentMessageId);
    if (null === $parent) {
      throw MessagingNotFoundException::message($query->parentMessageId);
    }

    $conversation = $this->conversations->findById($parent->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($parent->conversationId);
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

    return new ListRepliesResult(
      $this->messages->listRepliesByParent($query->parentMessageId, $query->page, $query->itemsPerPage),
      $memberId,
    );
  }
}
