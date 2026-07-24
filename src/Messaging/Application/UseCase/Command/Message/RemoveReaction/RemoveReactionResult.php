<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\RemoveReaction;

use Messaging\Application\Contract\Message\MessageView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase RemoveReactionResult.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveReactionResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param MessageView $message the message view
   */
  public function __construct(public MessageView $message)
  {
  }
}
