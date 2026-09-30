<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\ReadMarker\AcknowledgeDelivery;

use Shared\Application\Message\CommandMessage;

/** Confirms that another authenticated client received a specific message. */
final readonly class AcknowledgeDeliveryCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Identifies the caller, conversation and received message to acknowledge.
   *
   * @access public
   *
   * @param string $userId authenticated user acknowledging delivery
   * @param string $conversationId conversation containing the message
   * @param string $messageId message confirmed as received
   *
   * @return void
   */
  public function __construct(public string $userId, public string $conversationId, public string $messageId)
  {
  }
  // #endregion
}
