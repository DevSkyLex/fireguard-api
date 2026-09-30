<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\Presence\GetPresencePreferenceSubscription;

use Shared\Application\Message\QueryHandler;
use User\Application\Port\Outbound\PresencePreferenceRealtimePort;

/**
 * UseCase GetPresencePreferenceSubscriptionHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPresencePreferenceSubscriptionHandler implements QueryHandler
{
  /**
   * @since 1.0.0
   */
  public function __construct(private PresencePreferenceRealtimePort $realtime)
  {
  }

  /**
   * @since 1.0.0
   */
  public function __invoke(GetPresencePreferenceSubscriptionQuery $query): GetPresencePreferenceSubscriptionResult
  {

    $subscription = $this->realtime->subscribe($query->userId);

    return new GetPresencePreferenceSubscriptionResult($subscription->topic, $subscription->token, $subscription->expiresAt);
  }
}
