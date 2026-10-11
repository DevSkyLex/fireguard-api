<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Subscription\GetMessagingSubscription;

use Shared\Application\Message\QueryMessage;

/**
 * Class GetMessagingSubscriptionQuery
 *
 * Requests a scoped realtime subscription for the authenticated actor.
 *
 * @category UseCase
 */
final readonly class GetMessagingSubscriptionQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the actor and requested conversation before any token is minted.
   *
   * @access public
   *
   * @param string $userId authenticated actor
   * @param string $conversationId conversation requested by the actor
   *
   * @return void
   */
  public function __construct(public string $userId, public string $conversationId)
  {
  }
  // #endregion
}
