<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Operation;

/** Operation names for ephemeral typing and durable conversation receipts. */
final class ConversationSignalOperations
{
  /**
   * Constant TYPING
   */
  public const string TYPING = 'messaging_conversation_typing';

  /**
   * Constant DELIVERY
   */
  public const string DELIVERY = 'messaging_conversation_delivery';

  /**
   * Constant RECEIPTS
   */
  public const string RECEIPTS = 'messaging_conversation_receipts';
}
