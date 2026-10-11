<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Subscription\GetMessagingSubscription;

use Messaging\Application\Contract\Subscription\MessagingSubscription;
use Shared\Application\Message\ResultMessage;

/**
 * Class GetMessagingSubscriptionResult
 *
 * Returns transport-neutral data for the unchanged subscription HTTP representation.
 *
 * @category UseCase
 */
final readonly class GetMessagingSubscriptionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the token issued after successful conversation authorization.
   *
   * @access public
   *
   * @param MessagingSubscription $subscription scoped subscription
   *
   * @return void
   */
  public function __construct(public MessagingSubscription $subscription)
  {
  }
  // #endregion
}
