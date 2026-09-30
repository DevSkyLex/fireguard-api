<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\Presence\GetPresencePreferenceSubscription;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetPresencePreferenceSubscriptionResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPresencePreferenceSubscriptionResult implements ResultMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $topic, public string $token, public string $expiresAt)
  {
  }
}
