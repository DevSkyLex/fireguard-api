<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\EditMessage;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase EditMessageCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EditMessageCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $messageId the message id value
   * @param string $body the sanitized raw message body
   */
  public function __construct(
    public string $userId,
    public string $messageId,
    public string $body,
  ) {
  }
}
