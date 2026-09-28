<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\ReadMarker\AcknowledgeDelivery;

use Shared\Application\Message\ResultMessage;

/** The acknowledged message identifier. */
final readonly class AcknowledgeDeliveryResult implements ResultMessage
{
  public function __construct(public string $messageId)
  {
  }
}
