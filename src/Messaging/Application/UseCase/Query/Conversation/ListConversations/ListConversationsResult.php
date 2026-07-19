<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Conversation\ListConversations;

use Messaging\Application\Contract\Conversation\ConversationPage;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListConversationsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListConversationsResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ConversationPage $page the conversation page
   * @param array<string, int> $unreadCounts unread counts indexed by conversation id
   * @param list<string> $favoriteConversationIds the subset of this page's conversation
   *                                              ids favorited by the acting member (L1.5)
   */
  public function __construct(
    public ConversationPage $page,
    public array $unreadCounts,
    public array $favoriteConversationIds = [],
  ) {
  }
}
