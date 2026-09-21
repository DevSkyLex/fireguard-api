<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Command\Message\ControlAssistantAttempt;

use Assistant\Application\Contract\Message\AssistantMessageView;
use Shared\Application\Message\ResultMessage;

/** Result ControlAssistantAttemptResult. Canonical reply after the serialized action. */
final readonly class ControlAssistantAttemptResult implements ResultMessage
{
  public function __construct(public AssistantMessageView $message)
  {
  }
}
