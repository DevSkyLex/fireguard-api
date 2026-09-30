<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\ReadMarker;

use DateTimeImmutable;

/** A participant's confirmed delivery and read positions in one conversation. */
final readonly class ConversationReceiptPosition
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Captures a member’s confirmed delivery and read positions in a conversation.
   *
   * @access public
   *
   * @param string $memberId organization member whose positions are reported
   * @param ?string $deliveredMessageId last message confirmed as delivered, when available
   * @param ?DateTimeImmutable $deliveredThroughAt timestamp through which delivery was confirmed
   * @param ?string $readMessageId last message confirmed as read, when available
   * @param ?DateTimeImmutable $readThroughAt timestamp through which reading was confirmed
   *
   * @return void
   */
  public function __construct(
    public string $memberId,
    public ?string $deliveredMessageId,
    public ?DateTimeImmutable $deliveredThroughAt,
    public ?string $readMessageId,
    public ?DateTimeImmutable $readThroughAt,
  ) {
  }
  // #endregion
}
