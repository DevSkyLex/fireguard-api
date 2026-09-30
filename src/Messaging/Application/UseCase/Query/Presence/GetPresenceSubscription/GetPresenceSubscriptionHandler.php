<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Presence\GetPresenceSubscription;

use Messaging\Application\Port\Outbound\PresenceRealtimePort;
use Messaging\Application\Service\MessagingAccessPolicy;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetPresenceSubscriptionHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPresenceSubscriptionHandler implements QueryHandler
{
  /**
   * @since 1.0.0
   */
  public function __construct(private PresenceRealtimePort $realtime, private MessagingAccessPolicy $accessPolicy)
  {
  }

  /**
   * @since 1.0.0
   */
  public function __invoke(GetPresenceSubscriptionQuery $query): GetPresenceSubscriptionResult
  {
    $this->accessPolicy->assertCanReadPresence($query->userId, $query->organizationId);
    $subscription = $this->realtime->subscribe($query->organizationId);

    return new GetPresenceSubscriptionResult($subscription->topic, $subscription->token, $subscription->expiresAt);
  }
}
