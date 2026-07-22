<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Conversation\ListDirectConversations;

use Messaging\Application\Contract\Conversation\ConversationPage;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListDirectConversationsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListDirectConversationsResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ConversationPage $page the direct conversation page
   * @param array<string, string> $counterpartMemberIds the OTHER participant's
   *                                                    member id indexed by conversation id
   * @param array<string, int> $unreadCounts unread counts indexed by conversation id
   * @param list<string> $favoriteConversationIds the subset of this page's conversation
   *                                              ids favorited by the acting member
   */
  public function __construct(
    public ConversationPage $page,
    public array $counterpartMemberIds,
    public array $unreadCounts,
    public array $favoriteConversationIds = [],
  ) {
  }
}
