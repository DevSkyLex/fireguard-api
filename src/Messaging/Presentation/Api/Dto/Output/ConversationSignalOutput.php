<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

/** Acknowledges an accepted typing or delivery signal. */
final class ConversationSignalOutput
{
  public bool $accepted = true;
}
