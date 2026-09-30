<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Command\Message\ControlAssistantAttempt;

use Shared\Application\Message\CommandMessage;

/** Command ControlAssistantAttemptCommand. Only the requesting account can control its private reply. */
final readonly class ControlAssistantAttemptCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the member-scoped request to retry or cancel one assistant reply attempt.
   *
   * @access public
   *
   * @param string $actorUserId authenticated user whose private reply is being controlled
   * @param string $organizationId organization scope of the assistant thread
   * @param string $threadId member-private thread containing the reply
   * @param string $messageId assistant message whose attempt is being controlled
   * @param string $attemptId generation attempt targeted by this command
   * @param bool $retry whether to retry the failed attempt instead of cancelling it
   *
   * @return void
   */
  public function __construct(public string $actorUserId, public string $organizationId, public string $threadId, public string $messageId, public string $attemptId, public bool $retry)
  {
  }
  // #endregion
}
