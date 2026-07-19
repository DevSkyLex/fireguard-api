<?php

declare(strict_types=1);

namespace Notification\Application\Port\Outbound;

/**
 * Port RecipientDirectoryPort.
 *
 * Resolves a deliverable email address from a platform user identifier so
 * callers can request the EMAIL channel with only a `recipientUserId` — the
 * Notification module looks the address up itself instead of every caller
 * re-implementing the lookup.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface RecipientDirectoryPort
{
  /**
   * Returns the email address of a user, or null when the user does not
   * exist or cannot receive email.
   *
   * @param string $userId the platform user identifier
   *
   * @return ?string the email address, or null when unresolvable
   */
  public function emailForUserId(string $userId): ?string;
}
