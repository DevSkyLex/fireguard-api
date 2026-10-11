<?php

declare(strict_types=1);

namespace Session\Application\Port\Inbound;

/**
 * Revokes account sessions without exposing session aggregates.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface SessionRevocationPort
{
  public function revokeAllForUser(string $userId): int;
}
