<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\ReadMarker\AcknowledgeDelivery;

use Shared\Application\Message\ResultMessage;

/** The acknowledged message identifier. */
final readonly class AcknowledgeDeliveryResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Returns the identifier of the acknowledged message.
   *
   * @access public
   *
   * @param string $messageId identifier of the message whose delivery was acknowledged
   *
   * @return void
   */
  public function __construct(public string $messageId)
  {
  }
  // #endregion
}
