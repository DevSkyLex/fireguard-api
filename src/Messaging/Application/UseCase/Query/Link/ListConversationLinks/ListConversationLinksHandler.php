<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Link\ListConversationLinks;

use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingLinkRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListConversationLinksHandler.
 *
 * Gates the Links tab by the exact same access rule as a normal message
 * read — mirrors `ListConversationAttachmentsHandler`/`ListPinnedMessagesHandler`
 * byte for byte — via `MessagingAccessPolicy`, so there is a single access
 * path for reading a conversation's content.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListConversationLinksHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingLinkRepositoryPort $links the link repository port
   * @param MessagingSubjectResolverRegistry $resolvers the subject resolver registry
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingLinkRepositoryPort $links,
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
   *
   * @param ListConversationLinksQuery $query the query value
   *
   * @return ListConversationLinksResult the use case result
   */
  public function __invoke(ListConversationLinksQuery $query): ListConversationLinksResult
  {
    $conversation = $this->conversations->findById($query->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($query->conversationId);
    }

    // Membership is resolved BEFORE the visibility branch, not inside it.
    // `resolveActiveMemberId()` answers 404 for a caller outside the
    // conversation's organization, whereas `assertCanReadThread()` answers 403 —
    // which would confirm to an outsider that the conversation exists. Nine
    // sibling handlers already hoist it; these four did not.
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

    return new ListConversationLinksResult(
      $this->links->listByConversation($query->conversationId, $query->page, $query->itemsPerPage),
    );
  }
  // #endregion
}
