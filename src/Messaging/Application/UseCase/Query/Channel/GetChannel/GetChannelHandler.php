<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Channel\GetChannel;

use Messaging\Application\Port\Outbound\{MessagingConversationFavoriteRepositoryPort, MessagingConversationRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetChannelHandler.
 *
 * Reading a specific channel is participant-gated (or `.manage` bypass),
 * mirroring `GetConversationHandler`'s subject-permission gate for v1
 * threads.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetChannelHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingReadMarkerRepositoryPort $readMarkers the read marker repository port
   * @param MessagingConversationFavoriteRepositoryPort $favorites the conversation favorite repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
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
   * @param GetChannelQuery $query the query value
   *
   * @return GetChannelResult the use case result
   */
  public function __invoke(GetChannelQuery $query): GetChannelResult
  {
    $channel = $this->conversations->findChannelById($query->conversationId);
    if (null === $channel) {
      throw MessagingNotFoundException::conversation($query->conversationId);
    }

    $memberId = $this->accessPolicy->resolveActiveMemberId($channel->organizationId, $query->userId);
    $this->accessPolicy->assertCanReadChannel($query->userId, $channel->organizationId, $query->conversationId, $memberId);

    $unreadCounts = $this->readMarkers->unreadCounts($channel->organizationId, $memberId, [$channel->id]);
    $isFavorite = [] !== $this->favorites->findFavoritedConversationIds($memberId, [$channel->id]);

    return new GetChannelResult($channel, $unreadCounts[$channel->id] ?? 0, $isFavorite);
  }
}
