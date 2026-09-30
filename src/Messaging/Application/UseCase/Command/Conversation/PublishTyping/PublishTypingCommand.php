<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\PublishTyping;

use Shared\Application\Message\CommandMessage;

/** Announces a temporary typing state without storing draft content. */
final readonly class PublishTypingCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Identifies the user and conversation for a transient typing-state update.
   *
   * @access public
   *
   * @param string $userId authenticated user publishing the state
   * @param string $conversationId conversation receiving the state
   * @param bool $active whether the user is currently typing
   *
   * @return void
   */
  public function __construct(public string $userId, public string $conversationId, public bool $active)
  {
  }
  // #endregion
}
