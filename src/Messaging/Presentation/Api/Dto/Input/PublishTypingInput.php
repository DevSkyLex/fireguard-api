<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input;

/** The acting member's transient typing state. */
final class PublishTypingInput
{
  public bool $active = false;
}
