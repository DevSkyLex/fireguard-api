<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Presence\GetPresenceSubscription;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetPresenceSubscriptionResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPresenceSubscriptionResult implements ResultMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $topic, public string $token, public string $expiresAt)
  {
  }
}
