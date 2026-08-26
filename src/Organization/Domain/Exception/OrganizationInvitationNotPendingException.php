<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

/**
 * Exception OrganizationInvitationNotPendingException.
 *
 * Raised when an invitation cannot be acted on because it is no longer
 * pending — already accepted, already revoked, or expired.
 *
 * Mapped to 400, which is what the `InvalidArgumentException` it replaces
 * already answered. **409 is the more honest reading** — the invitation exists
 * and the request is well formed, the state simply forbids the action — but
 * changing it is a contract decision, not a refactor's side effect.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationInvitationNotPendingException extends RuntimeException
{
  // #region Methods
  /**
   * Method expired.
   *
   * Creates an exception for an invitation whose validity window has passed.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function expired(): self
  {
    return new self('Invitation has expired.');
  }

  /**
   * Method alreadyExpired.
   *
   * Creates an exception for a revocation attempt on an invitation that has
   * already lapsed. Distinct wording from {@see self::expired()} because the
   * caller's intent differs — accepting versus revoking — and both messages
   * are part of the published contract.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function alreadyExpired(): self
  {
    return new self('Invitation has already expired.');
  }

  /**
   * Method noLongerPending.
   *
   * Creates an exception for an invitation already accepted or revoked.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function noLongerPending(): self
  {
    return new self('Invitation is no longer pending.');
  }

  /**
   * Method onlyPendingCanBeRevoked.
   *
   * Creates an exception for a revocation targeting a decided invitation.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function onlyPendingCanBeRevoked(): self
  {
    return new self('Only pending invitations can be revoked.');
  }
  // #endregion
}
