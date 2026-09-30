<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\ReadMarker\GetConversationReceipts;

use Shared\Application\Message\QueryMessage;

/** Requests the current participants' receipt positions. */
final readonly class GetConversationReceiptsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Identifies the caller and conversation whose receipt positions are requested.
   *
   * @access public
   *
   * @param string $userId authenticated user requesting receipt positions
   * @param string $conversationId conversation whose participants' positions are queried
   *
   * @return void
   */
  public function __construct(public string $userId, public string $conversationId)
  {
  }
  // #endregion
}
