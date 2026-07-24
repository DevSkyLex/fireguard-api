<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\PinMessage;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase PinMessageCommand.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PinMessageCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.1.0
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
