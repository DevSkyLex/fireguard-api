<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\PublishTyping;

use Shared\Application\Message\ResultMessage;

/** The acting member whose typing state was announced. */
final readonly class PublishTypingResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Returns the organization member whose typing state was published.
   *
   * @access public
   *
   * @param string $memberId resolved active organization member identifier
   *
   * @return void
   */
  public function __construct(public string $memberId)
  {
  }
  // #endregion
}
