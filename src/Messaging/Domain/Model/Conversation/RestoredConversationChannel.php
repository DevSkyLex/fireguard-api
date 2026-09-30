<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Conversation;

use Messaging\Domain\ValueObject\ChannelName;

/** Optional channel fields restored from the same conversation record. */
final readonly class RestoredConversationChannel
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries optional channel-specific fields restored from a conversation.
   *
   * @access public
   *
   * @param ?ChannelName $name optional channel name
   * @param ?string $teamId optional team owning the channel
   * @param ?string $createdByMemberId optional member who created the channel
   * @param ?string $parentConversationId optional parent channel identifier
   *
   * @return void
   */
  public function __construct(
    public ?ChannelName $name = null,
    public ?string $teamId = null,
    public ?string $createdByMemberId = null,
    public ?string $parentConversationId = null,
  ) {
  }
  // #endregion
}
