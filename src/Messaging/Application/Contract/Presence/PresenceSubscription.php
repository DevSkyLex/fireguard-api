<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Presence;

/**
 * Contract PresenceSubscription.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PresenceSubscription
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $topic, public string $token, public string $expiresAt)
  {
  }
}
