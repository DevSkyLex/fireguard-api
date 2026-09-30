<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Command\Message\ControlAssistantAttempt;

use Assistant\Application\Contract\Message\AssistantMessageView;
use Shared\Application\Message\ResultMessage;

/** Result ControlAssistantAttemptResult. Canonical reply after the serialized action. */
final readonly class ControlAssistantAttemptResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the assistant message view produced by the attempt-control use case.
   *
   * @access public
   *
   * @param AssistantMessageView $message updated assistant message returned to the caller
   *
   * @return void
   */
  public function __construct(public AssistantMessageView $message)
  {
  }
  // #endregion
}
