<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Operation;

/** Operation names for ephemeral typing and durable conversation receipts. */
final class ConversationSignalOperations
{
  public const string TYPING = 'messaging_conversation_typing';

  public const string DELIVERY = 'messaging_conversation_delivery';

  public const string RECEIPTS = 'messaging_conversation_receipts';
}
