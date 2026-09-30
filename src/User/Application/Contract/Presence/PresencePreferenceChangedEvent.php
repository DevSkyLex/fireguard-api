<?php

declare(strict_types=1);

namespace User\Application\Contract\Presence;

/**
 * Published after the auth database write commits.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PresencePreferenceChangedEvent
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $userId, public bool $doNotDisturb, public int $revision, public bool $invisible = false)
  {
  }
}
