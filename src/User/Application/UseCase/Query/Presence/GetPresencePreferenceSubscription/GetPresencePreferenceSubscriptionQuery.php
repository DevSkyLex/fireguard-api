<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\Presence\GetPresencePreferenceSubscription;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetPresencePreferenceSubscriptionQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPresencePreferenceSubscriptionQuery implements QueryMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $userId)
  {
  }
}
