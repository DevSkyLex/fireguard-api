<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Presence\GetPresenceSubscription;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetPresenceSubscriptionQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPresenceSubscriptionQuery implements QueryMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $userId, public string $organizationId)
  {
  }
}
