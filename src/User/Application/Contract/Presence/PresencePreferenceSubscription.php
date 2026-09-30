<?php

declare(strict_types=1);

namespace User\Application\Contract\Presence;

/**
 * Contract PresencePreferenceSubscription.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PresencePreferenceSubscription
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $topic, public string $token, public string $expiresAt)
  {
  }
}
