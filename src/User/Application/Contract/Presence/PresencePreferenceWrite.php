<?php

declare(strict_types=1);

namespace User\Application\Contract\Presence;

/**
 * Persistence outcome distinguishes idempotent saves from actual changes.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PresencePreferenceWrite
{
  /**
   * @since 1.0.0
   */
  public function __construct(public PresencePreference $preference, public bool $changed)
  {
  }
}
