<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Conversation\GetConversation;

use Messaging\Application\Port\Outbound\{MessagingConversationFavoriteRepositoryPort, MessagingConversationRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetConversationHandler.
 *
 * Opening a specific thread requires the subject's own read permission in
 * addition to `organization.messaging.read` — the finer gate
 * `ListConversations` deliberately skips for cost. A `visibility=PARTICIPANTS`
 * channel is gated by participant membership instead (no subject to
 * resolve) — this also makes `/api/conversations/{id}/subscription`
 * channel-aware automatically, since its provider reuses this handler
 * through the query bus. The owning organization is derived from the
 * loaded conversation, not supplied by the caller.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetConversationHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingReadMarkerRepositoryPort $readMarkers the read marker repository port
   * @param MessagingConversationFavoriteRepositoryPort $favorites the conversation favorite repository port
   * @param MessagingSubjectResolverRegistry $resolvers the subject resolver registry
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingReadMarkerRepositoryPort $readMarkers,
    private MessagingConversationFavoriteRepositoryPort $favorites,
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetConversationQuery $query the query value
   *
   * @return GetConversationResult the use case result
   */
  public function __invoke(GetConversationQuery $query): GetConversationResult
  {
    $conversation = $this->conversations->findById($query->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($query->conversationId);
    }

    $memberId = $this->accessPolicy->resolveActiveMemberId($conversation->organizationId, $query->userId);

    $subjectLabel = null;
    if (ConversationVisibility::PARTICIPANTS->value === $conversation->visibility) {
      $this->accessPolicy->assertCanReadChannel($query->userId, $conversation->organizationId, $conversation->id, $memberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.read';
      if (null !== $conversation->subjectId) {
        $subjectType = MessagingSubjectType::from($conversation->subjectType);
        $resolution = $this->resolvers->resolve($subjectType, $conversation->organizationId, $conversation->subjectId);
        $subjectLabel = $resolution->label;
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }

      $this->accessPolicy->assertCanReadThread($query->userId, $conversation->organizationId, $requiredSubjectPermission);
    }

    $unreadCounts = $this->readMarkers->unreadCounts($conversation->organizationId, $memberId, [$conversation->id]);
    $isFavorite = [] !== $this->favorites->findFavoritedConversationIds($memberId, [$conversation->id]);

    return new GetConversationResult($conversation, $subjectLabel, $unreadCounts[$conversation->id] ?? 0, $isFavorite);
  }
}
