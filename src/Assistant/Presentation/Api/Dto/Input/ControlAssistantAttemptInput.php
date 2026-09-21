<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Dto\Input;

use Assistant\Presentation\Api\Serialization\AssistantSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Input ControlAssistantAttemptInput. Expected identity prevents a stale action from affecting the next attempt. */
final class ControlAssistantAttemptInput
{
  #[Groups([AssistantSerializationGroup::WRITE])]
  #[Assert\NotBlank]
  #[Assert\Uuid]
  public string $attemptId = '';
}
