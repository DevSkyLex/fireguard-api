<?php

declare(strict_types=1);

namespace User\Application\Contract\Presence;

/**
 * Persistent presence preference; revision zero is the default state.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PresencePreference
{
  /**
   * @since 1.0.0
   */
  public function __construct(public bool $doNotDisturb = false, public int $revision = 0, public bool $invisible = false)
  {
  }
}
