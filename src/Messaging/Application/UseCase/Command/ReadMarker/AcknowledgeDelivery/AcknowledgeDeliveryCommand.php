<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\ReadMarker\AcknowledgeDelivery;

use Shared\Application\Message\CommandMessage;

/** Confirms that another authenticated client received a specific message. */
final readonly class AcknowledgeDeliveryCommand implements CommandMessage
{
  public function __construct(public string $userId, public string $conversationId, public string $messageId)
  {
  }
}
