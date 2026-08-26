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
 * Mapped to **409**, arbitrated 2026-08-26. The invitation exists and the
 * request is well formed; its state is what forbids the action. It answered
 * 400 only because it was a bare `InvalidArgumentException` and one generic
 * catch decided for it.
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
