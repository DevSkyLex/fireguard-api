<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Query\Thread\GetAssistantThreadSubscription;

use Shared\Application\Message\ResultMessage;

/**
 * Class GetAssistantThreadSubscriptionResult
 *
 * Returns a scoped subscriber credential and its stable topic as pure scalars.
 *
 * @category UseCase
 */
final readonly class GetAssistantThreadSubscriptionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $token scoped subscriber credential
   * @param string $topic private authorized topic
   *
   * @return void
   */
  public function __construct(public string $token, public string $topic)
  {
  }
  // #endregion
}
