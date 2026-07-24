<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Message\ListSavedMessages;

use Messaging\Application\Contract\Message\MessagePage;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListSavedMessagesResult.
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListSavedMessagesResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param MessagePage $page the saved message page
   * @param string $currentMemberId the reading member's identifier, so the
   *                                Presentation mapper can compute
   *                                `isSaved`/`reactions[].reactedByMe`
   *                                relative to THIS member
   */
  public function __construct(
    public MessagePage $page,
    public string $currentMemberId,
  ) {
  }
}
