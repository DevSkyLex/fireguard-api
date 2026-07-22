<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Conversation\ListDirectConversations;

use Messaging\Application\Port\Outbound\{MessagingConversationFavoriteRepositoryPort, MessagingConversationRepositoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Shared\Application\Message\QueryHandler;

use function array_map;

/**
 * UseCase ListDirectConversationsHandler.
 *
 * Lists the DIRECT (1-to-1) conversations the acting member participates in
 * (an INNER JOIN on `messaging_participants`, pushed down to SQL) — gated by
 * `organization.messaging.read` alone, identical to
 * `ListChannelsHandler`/`ListConversationsHandler`'s no-per-row-check-for-cost
 * stance: the participant join itself already restricts the result set to
 * conversations the caller is part of, which is what preserves the privacy
 * invariant behind excluding `DIRECT` from `GET /api/conversations` (see
 * `MessagingConversationRepository::list()`) — a member can never discover a
 * direct conversation they are not a participant of through this endpoint
 * either.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListDirectConversationsHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingParticipantRepositoryPort $participants the participant repository port
   * @param MessagingReadMarkerRepositoryPort $readMarkers the read marker repository port
   * @param MessagingConversationFavoriteRepositoryPort $favorites the conversation favorite repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingParticipantRepositoryPort $participants,
    private MessagingReadMarkerRepositoryPort $readMarkers,
    private MessagingConversationFavoriteRepositoryPort $favorites,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ListDirectConversationsQuery $query the query value
   *
   * @return ListDirectConversationsResult the use case result
   */
  public function __invoke(ListDirectConversationsQuery $query): ListDirectConversationsResult
  {
    $this->accessPolicy->assertCanListConversations($query->userId, $query->organizationId);

    $memberId = $this->accessPolicy->resolveActiveMemberId($query->organizationId, $query->userId);

    $page = $this->conversations->listDirectConversationsForMember(
      $query->organizationId,
      $memberId,
      $query->isArchived,
      $query->page,
      $query->itemsPerPage,
    );

    $ids = array_map(static fn ($view): string => $view->id, $page->items);
    $counterpartMemberIds = [] === $ids ? [] : $this->participants->findCounterpartMemberIds($ids, $memberId);
    $unreadCounts = [] === $ids ? [] : $this->readMarkers->unreadCounts($query->organizationId, $memberId, $ids);
    $favoriteConversationIds = [] === $ids ? [] : $this->favorites->findFavoritedConversationIds($memberId, $ids);

    return new ListDirectConversationsResult($page, $counterpartMemberIds, $unreadCounts, $favoriteConversationIds);
  }
}
