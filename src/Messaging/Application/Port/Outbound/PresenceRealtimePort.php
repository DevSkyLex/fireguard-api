<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Outbound;

use Messaging\Application\Contract\Presence\PresenceSubscription;

/**
 * Port PresenceRealtimePort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface PresenceRealtimePort
{
  /**
   * @since 1.0.0
   */
  public function subscribe(string $id): PresenceSubscription;

  /**
   * @since 1.0.0
   */
  public function publish(string $id, string $memberId): void;
}
