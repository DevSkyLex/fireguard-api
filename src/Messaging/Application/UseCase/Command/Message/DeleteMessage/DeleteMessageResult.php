<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\DeleteMessage;

use Messaging\Application\Contract\Message\MessageView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteMessageResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteMessageResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessageView $message the message view
   */
  public function __construct(public MessageView $message)
  {
  }
}
