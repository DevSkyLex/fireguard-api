<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\PostReply;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase PostReplyCommand.
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PostReplyCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param string $userId the acting user id value
   * @param string $parentMessageId the parent (root) message id value
   * @param string $body the sanitized raw reply body
   */
  public function __construct(
    public string $userId,
    public string $parentMessageId,
    public string $body,
  ) {
  }
}
