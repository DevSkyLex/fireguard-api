<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\UnsaveMessage;

use Messaging\Application\Contract\Message\MessageView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UnsaveMessageResult.
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UnsaveMessageResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param MessageView $message the message view
   */
  public function __construct(public MessageView $message)
  {
  }
}
