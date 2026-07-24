<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Channel\ListChannelParticipants;

use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListChannelParticipantsHandler.
 *
 * Lists a channel's participants — participant- or `.manage`-gated
 * (same rule as reading the channel itself).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListChannelParticipantsHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingParticipantRepositoryPort $participants the participant repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingParticipantRepositoryPort $participants,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ListChannelParticipantsQuery $query the query value
   *
   * @return ListChannelParticipantsResult the use case result
   */
  public function __invoke(ListChannelParticipantsQuery $query): ListChannelParticipantsResult
  {
    $channel = $this->conversations->findChannelById($query->conversationId);
    if (null === $channel) {
      throw MessagingNotFoundException::conversation($query->conversationId);
    }

    $memberId = $this->accessPolicy->resolveActiveMemberId($channel->organizationId, $query->userId);
    $this->accessPolicy->assertCanReadChannel($query->userId, $channel->organizationId, $query->conversationId, $memberId);

    return new ListChannelParticipantsResult($this->participants->listParticipants($query->conversationId));
  }
}
