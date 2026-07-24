<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\UnsaveMessage;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UnsaveMessageCommand.
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UnsaveMessageCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param string $userId the acting user id value
   * @param string $messageId the message id value
   */
  public function __construct(
    public string $userId,
    public string $messageId,
  ) {
  }
}
