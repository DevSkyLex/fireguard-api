<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Query\Thread\GetAssistantThreadSubscription;

use Shared\Application\Message\QueryMessage;

/**
 * Class GetAssistantThreadSubscriptionQuery
 *
 * Requests a subscription under the same actor and organization scope as a thread read.
 *
 * @category UseCase
 */
final readonly class GetAssistantThreadSubscriptionQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $organizationId requested organization
   * @param string $threadId member-private thread
   * @param string $actorUserId authenticated actor
   *
   * @return void
   */
  public function __construct(public string $organizationId, public string $threadId, public string $actorUserId)
  {
  }
  // #endregion
}
