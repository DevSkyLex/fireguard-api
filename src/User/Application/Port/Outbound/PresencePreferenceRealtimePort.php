<?php

declare(strict_types=1);

namespace User\Application\Port\Outbound;

use User\Application\Contract\Presence\PresencePreferenceSubscription;

/**
 * Port PresencePreferenceRealtimePort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface PresencePreferenceRealtimePort
{
  /**
   * @since 1.0.0
   */
  public function subscribe(string $id): PresencePreferenceSubscription;

  /**
   * @since 1.0.0
   */
  public function publish(string $id, bool $doNotDisturb, int $revision, bool $invisible = false): void;
}
