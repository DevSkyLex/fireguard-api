<?php

declare(strict_types=1);

namespace User\Application\Port\Inbound;

use User\Application\Contract\Presence\PresencePreference;

/**
 * Published batch read; missing users have the default preference.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface PresencePreferenceReaderPort
{
  /**
   * @param list<string> $userIds
   *
   * @return array<string, PresencePreference>
   */
  public function readMany(array $userIds): array;
}
