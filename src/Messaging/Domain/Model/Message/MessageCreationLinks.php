<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Message;

use Messaging\Domain\ValueObject\MessageReference;

/** The optional thread parent and structured resource links of a new message. */
final readonly class MessageCreationLinks
{
  /**
   * @param list<MessageReference> $references
   */
  public function __construct(
    public ?string $parentMessageId = null,
    public array $references = [],
  ) {
  }
}
