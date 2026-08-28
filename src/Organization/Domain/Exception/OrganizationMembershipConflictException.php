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
  // #region Constants
  /**
   * Constant CONFLICT_PENDING_INVITATION.
   *
   * Discriminator value: a pending invitation already exists for the email.
   *
   * @since 1.1.0
   *
   * @var string CONFLICT_PENDING_INVITATION
   */
  public const string CONFLICT_PENDING_INVITATION = 'pending_invitation';

  /**
   * Constant CONFLICT_ACTIVE_MEMBER.
   *
   * Discriminator value: the user is already an active member.
   *
   * @since 1.1.0
   *
   * @var string CONFLICT_ACTIVE_MEMBER
   */
  public const string CONFLICT_ACTIVE_MEMBER = 'active_member';
  // #endregion

  // #region Properties
  /**
   * Property conflict.
   *
   * Which of the two conflicting states raised the exception — lets a
   * programmatic caller (the member-invitation provisioning port consumed by
   * the bulk CSV import) report "already invited" and "already a member" as
   * distinct outcomes without parsing the message.
   *
   * @since 1.1.0
   */
  private string $conflict = self::CONFLICT_ACTIVE_MEMBER;
  // #endregion

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
    $exception = new self('A pending invitation already exists for this email.');
    $exception->conflict = self::CONFLICT_PENDING_INVITATION;

    return $exception;
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
    $exception = new self('User is already an active member of this organization.');
    $exception->conflict = self::CONFLICT_ACTIVE_MEMBER;

    return $exception;
  }

  /**
   * Method conflict.
   *
   * @since 1.1.0
   *
   * @return string the conflict discriminator (`pending_invitation`|`active_member`)
   */
  public function conflict(): string
  {
    return $this->conflict;
  }
  // #endregion
}
