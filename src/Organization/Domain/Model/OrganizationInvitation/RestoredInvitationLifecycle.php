<?php

declare(strict_types=1);

namespace Organization\Domain\Model\OrganizationInvitation;

use DateTimeImmutable;
use Organization\Domain\ValueObject\OrganizationInvitationStatus;

/** Persisted invitation status and the matching actor/timestamp pairs. */
final readonly class RestoredInvitationLifecycle
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted invitation status and its acceptance or revocation history.
   *
   * @access public
   *
   * @param OrganizationInvitationStatus $status current invitation status
   * @param DateTimeImmutable $expiresAt timestamp when the invitation expires
   * @param ?DateTimeImmutable $acceptedAt optional time when the invitation was accepted
   * @param ?string $acceptedByUserId optional user who accepted the invitation
   * @param ?DateTimeImmutable $revokedAt optional time when the invitation was revoked
   * @param ?string $revokedByUserId optional user who revoked the invitation
   *
   * @return void
   */
  public function __construct(
    public OrganizationInvitationStatus $status,
    public DateTimeImmutable $expiresAt,
    public ?DateTimeImmutable $acceptedAt = null,
    public ?string $acceptedByUserId = null,
    public ?DateTimeImmutable $revokedAt = null,
    public ?string $revokedByUserId = null,
  ) {
  }
  // #endregion
}
