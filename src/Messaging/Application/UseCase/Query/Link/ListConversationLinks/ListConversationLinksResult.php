<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Link\ListConversationLinks;

use Messaging\Application\Contract\Link\MessagingLinkPage;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListConversationLinksResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListConversationLinksResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingLinkPage $page the link page
   */
  public function __construct(
    public MessagingLinkPage $page,
  ) {
  }
}
