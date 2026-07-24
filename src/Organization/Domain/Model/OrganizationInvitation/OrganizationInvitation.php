<?php

declare(strict_types=1);

namespace Organization\Domain\Model\OrganizationInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationInvitationStatus};
use Shared\Domain\ValueObject\Email;

/**
 * Model OrganizationInvitation.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationInvitation
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationInvitation class.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationId $id the invitation identifier
   * @param OrganizationId $organizationId the organization identifier
   * @param Email $email the invited email
   * @param string $tokenHash the hashed invitation token
   * @param string $invitedByUserId the inviter user identifier
   * @param OrganizationInvitationStatus $status the invitation status
   * @param DateTimeImmutable $expiresAt the expiration datetime
   * @param DateTimeImmutable $createdAt the creation datetime
   * @param DateTimeImmutable $updatedAt the update datetime
   * @param ?DateTimeImmutable $acceptedAt the acceptance datetime
   * @param ?string $acceptedByUserId the acceptor user identifier
   * @param ?DateTimeImmutable $revokedAt the revocation datetime
   * @param ?string $revokedByUserId the revoker user identifier
   */
  private function __construct(
    private OrganizationInvitationId $id,
    private OrganizationId $organizationId,
    private Email $email,
    private string $tokenHash,
    private string $invitedByUserId,
    private OrganizationInvitationStatus $status,
    private DateTimeImmutable $expiresAt,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private ?DateTimeImmutable $acceptedAt = null,
    private ?string $acceptedByUserId = null,
    private ?DateTimeImmutable $revokedAt = null,
    private ?string $revokedByUserId = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new organization invitation aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationId $id the invitation identifier
   * @param OrganizationId $organizationId the organization identifier
   * @param Email $email the invited email
   * @param string $tokenHash the hashed invitation token
   * @param string $invitedByUserId the inviter user identifier
   * @param DateTimeImmutable $expiresAt the expiration datetime
   *
   * @return self the created invitation aggregate
   */
  public static function create(
    OrganizationInvitationId $id,
    OrganizationId $organizationId,
    Email $email,
    string $tokenHash,
    string $invitedByUserId,
    DateTimeImmutable $expiresAt,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      organizationId: $organizationId,
      email: $email,
      tokenHash: $tokenHash,
      invitedByUserId: $invitedByUserId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: $expiresAt,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes an organization invitation aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationId $id the invitation identifier
   * @param OrganizationId $organizationId the organization identifier
   * @param Email $email the invited email
   * @param string $tokenHash the hashed invitation token
   * @param string $invitedByUserId the inviter user identifier
   * @param OrganizationInvitationStatus $status the invitation status
   * @param DateTimeImmutable $expiresAt the expiration datetime
   * @param DateTimeImmutable $createdAt the creation datetime
   * @param DateTimeImmutable $updatedAt the update datetime
   * @param ?DateTimeImmutable $acceptedAt the acceptance datetime
   * @param ?string $acceptedByUserId the acceptor user identifier
   * @param ?DateTimeImmutable $revokedAt the revocation datetime
   * @param ?string $revokedByUserId the revoker user identifier
   *
   * @return self the reconstituted invitation aggregate
   */
  public static function reconstitute(
    OrganizationInvitationId $id,
    OrganizationId $organizationId,
    Email $email,
    string $tokenHash,
    string $invitedByUserId,
    OrganizationInvitationStatus $status,
    DateTimeImmutable $expiresAt,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?DateTimeImmutable $acceptedAt = null,
    ?string $acceptedByUserId = null,
    ?DateTimeImmutable $revokedAt = null,
    ?string $revokedByUserId = null,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      email: $email,
      tokenHash: $tokenHash,
      invitedByUserId: $invitedByUserId,
      status: $status,
      expiresAt: $expiresAt,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      acceptedAt: $acceptedAt,
      acceptedByUserId: $acceptedByUserId,
      revokedAt: $revokedAt,
      revokedByUserId: $revokedByUserId,
    );
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): OrganizationInvitationId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): OrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method email.
   *
   * @since 1.0.0
   */
  public function email(): Email
  {
    return $this->email;
  }

  /**
   * Method tokenHash.
   *
   * @since 1.0.0
   */
  public function tokenHash(): string
  {
    return $this->tokenHash;
  }

  /**
   * Method invitedByUserId.
   *
   * @since 1.0.0
   */
  public function invitedByUserId(): string
  {
    return $this->invitedByUserId;
  }

  /**
   * Method status.
   *
   * @since 1.0.0
   */
  public function status(): OrganizationInvitationStatus
  {
    return $this->status;
  }

  /**
   * Method expiresAt.
   *
   * @since 1.0.0
   */
  public function expiresAt(): DateTimeImmutable
  {
    return $this->expiresAt;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * @since 1.0.0
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method acceptedAt.
   *
   * @since 1.0.0
   */
  public function acceptedAt(): ?DateTimeImmutable
  {
    return $this->acceptedAt;
  }

  /**
   * Method acceptedByUserId.
   *
   * @since 1.0.0
   */
  public function acceptedByUserId(): ?string
  {
    return $this->acceptedByUserId;
  }

  /**
   * Method revokedAt.
   *
   * @since 1.0.0
   */
  public function revokedAt(): ?DateTimeImmutable
  {
    return $this->revokedAt;
  }

  /**
   * Method revokedByUserId.
   *
   * @since 1.0.0
   */
  public function revokedByUserId(): ?string
  {
    return $this->revokedByUserId;
  }

  /**
   * Method isExpired.
   *
   * Returns whether invitation is expired at a given point in time.
   *
   * @since 1.0.0
   *
   * @param ?DateTimeImmutable $at the reference datetime
   *
   * @return bool true when expired, false otherwise
   */
  public function isExpired(?DateTimeImmutable $at = null): bool
  {
    $now = $at ?? new DateTimeImmutable();

    return $this->expiresAt < $now;
  }

  /**
   * Method accept.
   *
   * Marks the invitation as accepted.
   *
   * @since 1.0.0
   *
   * @param string $acceptedByUserId the acceptor user identifier
   * @param ?DateTimeImmutable $acceptedAt the acceptance datetime
   */
  public function accept(string $acceptedByUserId, ?DateTimeImmutable $acceptedAt = null): void
  {
    if (!$this->status->isPending()) {
      throw new InvalidArgumentException('Only pending invitations can be accepted.');
    }

    $now = $acceptedAt ?? new DateTimeImmutable();

    if ($this->isExpired($now)) {
      $this->expire($now);

      throw new InvalidArgumentException('Invitation has expired.');
    }

    $this->status = OrganizationInvitationStatus::ACCEPTED;
    $this->acceptedAt = $now;
    $this->acceptedByUserId = $acceptedByUserId;
    $this->updatedAt = $now;
  }

  /**
   * Method revoke.
   *
   * Marks the invitation as revoked.
   *
   * @since 1.0.0
   *
   * @param string $revokedByUserId the revoker user identifier
   * @param ?DateTimeImmutable $revokedAt the revocation datetime
   */
  public function revoke(string $revokedByUserId, ?DateTimeImmutable $revokedAt = null): void
  {
    if (!$this->status->isPending()) {
      throw new InvalidArgumentException('Only pending invitations can be revoked.');
    }

    $now = $revokedAt ?? new DateTimeImmutable();
    $this->status = OrganizationInvitationStatus::REVOKED;
    $this->revokedAt = $now;
    $this->revokedByUserId = $revokedByUserId;
    $this->updatedAt = $now;
  }

  /**
   * Method renew.
   *
   * Reissues a pending or expired invitation with a fresh token and expiry,
   * returning it to the pending state so it can be accepted again.
   *
   * @since 1.0.0
   *
   * @param string $newTokenHash the new hashed invitation token
   * @param DateTimeImmutable $newExpiresAt the new expiration datetime
   * @param ?DateTimeImmutable $renewedAt the renewal datetime
   */
  public function renew(string $newTokenHash, DateTimeImmutable $newExpiresAt, ?DateTimeImmutable $renewedAt = null): void
  {
    if (OrganizationInvitationStatus::PENDING !== $this->status && OrganizationInvitationStatus::EXPIRED !== $this->status) {
      throw new InvalidArgumentException('Only pending or expired invitations can be resent.');
    }

    $now = $renewedAt ?? new DateTimeImmutable();
    $this->status = OrganizationInvitationStatus::PENDING;
    $this->tokenHash = $newTokenHash;
    $this->expiresAt = $newExpiresAt;
    $this->updatedAt = $now;
  }

  /**
   * Method expire.
   *
   * Marks the invitation as expired.
   *
   * @since 1.0.0
   *
   * @param ?DateTimeImmutable $expiredAt the expiration datetime
   */
  public function expire(?DateTimeImmutable $expiredAt = null): void
  {
    if (!$this->status->isPending()) {
      return;
    }

    $now = $expiredAt ?? new DateTimeImmutable();

    if ($this->expiresAt <= $now) {
      $this->status = OrganizationInvitationStatus::EXPIRED;
      $this->updatedAt = $now;
    }
  }
  // #endregion
}
