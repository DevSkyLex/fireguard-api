<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

/**
 * Exception OrganizationMembershipConflictException.
 *
 * Raised when an invitation cannot be issued because the organization is
 * already in the state the invitation would create: a pending invitation for
 * that address exists, or the user is already an active member.
 *
 * Mapped to 409. The request is well formed and the caller is entitled — the
 * organization's current state is what forbids it, which is what 409 means.
 * It answered 400 until 2026-08-26.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationMembershipConflictException extends RuntimeException
{
  // #region Methods
  /**
   * Method pendingInvitationExists.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function pendingInvitationExists(): self
  {
    return new self('A pending invitation already exists for this email.');
  }

  /**
   * Method alreadyAnActiveMember.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function alreadyAnActiveMember(): self
  {
    return new self('User is already an active member of this organization.');
  }
  // #endregion
}
