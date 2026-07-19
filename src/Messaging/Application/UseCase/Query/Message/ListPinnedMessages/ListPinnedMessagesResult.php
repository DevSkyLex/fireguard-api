<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Message\ListPinnedMessages;

use Messaging\Application\Contract\Message\MessagePage;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListPinnedMessagesResult.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListPinnedMessagesResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param MessagePage $page the pinned message page
   * @param string $currentMemberId the reading member's identifier, so the
   *                                Presentation mapper can compute
   *                                `reactions[].reactedByMe` relative to
   *                                THIS member
   */
  public function __construct(
    public MessagePage $page,
    public string $currentMemberId,
  ) {
  }
}
