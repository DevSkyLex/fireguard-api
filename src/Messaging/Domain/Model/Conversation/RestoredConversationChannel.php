<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Conversation;

use Messaging\Domain\ValueObject\ChannelName;

/** Optional channel fields restored from the same conversation record. */
final readonly class RestoredConversationChannel
{
  public function __construct(
    public ?ChannelName $name = null,
    public ?string $teamId = null,
    public ?string $createdByMemberId = null,
    public ?string $parentConversationId = null,
  ) {
  }
}
