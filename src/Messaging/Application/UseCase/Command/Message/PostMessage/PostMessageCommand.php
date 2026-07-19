<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\PostMessage;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase PostMessageCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PostMessageCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $conversationId the conversation id value
   * @param string $body the sanitized raw message body
   */
  public function __construct(
    public string $userId,
    public string $conversationId,
    public string $body,
  ) {
  }
}
