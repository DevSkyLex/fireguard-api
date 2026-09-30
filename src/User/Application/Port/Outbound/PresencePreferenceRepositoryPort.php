<?php

declare(strict_types=1);

namespace User\Application\Port\Outbound;

use User\Application\Contract\Presence\{PresencePreference, PresencePreferenceWrite};

/**
 * Port PresencePreferenceRepositoryPort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface PresencePreferenceRepositoryPort
{
  /**
   * @since 1.0.0
   */
  public function get(string $userId): PresencePreference;

  /**
   * Returns only after the auth write commits; unchanged values retain their revision.
   */
  public function save(string $userId, ?bool $doNotDisturb, ?bool $invisible = null): PresencePreferenceWrite;
}
