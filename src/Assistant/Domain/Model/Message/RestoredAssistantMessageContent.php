<?php

declare(strict_types=1);

namespace Assistant\Domain\Model\Message;

use Assistant\Domain\ValueObject\AssistantMessageStatus;

/** Persisted content and generation result of an assistant message. */
final readonly class RestoredAssistantMessageContent
{
  public function __construct(
    public string $body,
    public AssistantMessageStatus $status,
    public ?string $errorCode,
    public ?int $tokenCount,
  ) {
  }
}
