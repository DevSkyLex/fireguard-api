<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Channel\ListChannels;

use Messaging\Application\Port\Outbound\{MessagingConversationFavoriteRepositoryPort, MessagingConversationRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Shared\Application\Message\QueryHandler;

use function array_map;

/**
 * UseCase ListChannelsHandler.
 *
 * Lists the channels the acting member participates in (an INNER JOIN on
 * `messaging_participants`, pushed down to SQL) — gated by
 * `organization.messaging.read` alone; the participant join itself already
 * restricts the result set, mirroring `ListConversationsHandler`'s
 * no-per-row-check-for-cost stance.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListChannelsHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingConversationFavoriteRepositoryPort $favorites the conversation favorite repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingConversationFavoriteRepositoryPort $favorites,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ListChannelsQuery $query the query value
   *
   * @return ListChannelsResult the use case result
   */
  public function __invoke(ListChannelsQuery $query): ListChannelsResult
  {
    $this->accessPolicy->assertCanListConversations($query->userId, $query->organizationId);

    $memberId = $this->accessPolicy->resolveActiveMemberId($query->organizationId, $query->userId);

    $page = $this->conversations->listChannelsForMember(
      $query->organizationId,
      $memberId,
      $query->isArchived,
      $query->page,
      $query->itemsPerPage,
    );

    $ids = array_map(static fn ($view): string => $view->id, $page->items);
    $favoriteChannelIds = [] === $ids ? [] : $this->favorites->findFavoritedConversationIds($memberId, $ids);

    return new ListChannelsResult($page, $favoriteChannelIds);
  }
}
