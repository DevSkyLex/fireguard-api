<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\PostReply;

use Messaging\Application\Contract\Message\MessageView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase PostReplyResult.
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PostReplyResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param MessageView $message the reply's message view
   * @param string $currentMemberId the replying member's identifier, so the
   *                                Presentation mapper can compute
   *                                `reactions[].reactedByMe` relative to
   *                                THIS member
   */
  public function __construct(
    public MessageView $message,
    public string $currentMemberId,
  ) {
  }
}
