<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\AddReaction;

use Messaging\Application\Contract\Message\MessageView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddReactionResult.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddReactionResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param MessageView $message the message view
   * @param string $currentMemberId the reacting member's identifier, so the
   *                                Presentation mapper can compute
   *                                `reactedByMe` relative to THIS member and
   *                                never leak another member's flag
   */
  public function __construct(
    public MessageView $message,
    public string $currentMemberId,
  ) {
  }
}
