<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\AddReaction;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddReactionCommand.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddReactionCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param string $userId the acting user id value
   * @param string $messageId the message id value
   * @param string $emoji the raw reaction emoji
   */
  public function __construct(
    public string $userId,
    public string $messageId,
    public string $emoji,
  ) {
  }
}
