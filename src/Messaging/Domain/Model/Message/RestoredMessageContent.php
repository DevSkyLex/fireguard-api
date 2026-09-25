<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Message;

use Messaging\Domain\ValueObject\MessageReference;

/** Body, mentions and structured references stored for one message. */
final readonly class RestoredMessageContent
{
  /**
   * @param list<string> $mentions
   * @param list<MessageReference> $references
   */
  public function __construct(
    public string $body,
    public array $mentions,
    public array $references = [],
  ) {
  }
}
