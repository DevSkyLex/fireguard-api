<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

/** A current participant's last confirmed delivery and read message positions. */
final class ConversationReceiptPositionOutput
{
  // #region Properties
  /**
   * Property memberId
   */
  public string $memberId = '';

  /**
   * Property deliveredMessageId
   */
  public ?string $deliveredMessageId = null;

  /**
   * Property deliveredThroughAt
   */
  public ?string $deliveredThroughAt = null;

  /**
   * Property readMessageId
   */
  public ?string $readMessageId = null;

  /**
   * Property readThroughAt
   */
  public ?string $readThroughAt = null;
  // #endregion
}
