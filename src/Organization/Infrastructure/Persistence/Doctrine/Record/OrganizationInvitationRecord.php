<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record OrganizationInvitationRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization_invitations')]
#[ORM\Index(name: 'idx_organization_invitation_organization', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_organization_invitation_email', columns: ['email'])]
#[ORM\Index(name: 'idx_organization_invitation_status', columns: ['status'])]
#[ORM\Index(name: 'idx_organization_invitation_organization_status', columns: ['organization_id', 'status'])]
#[ORM\UniqueConstraint(name: 'uniq_organization_invitation_token_hash', columns: ['token_hash'])]
class OrganizationInvitationRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property organization.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class)]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  /**
   * Property email.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'email', type: 'string', length: 320)]
  public string $email;

  /**
   * Property tokenHash.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'token_hash', type: 'string', length: 64)]
  public string $tokenHash;

  /**
   * Property invitedByUserId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'invited_by_user_id', type: 'string', length: 36)]
  public string $invitedByUserId;

  /**
   * Property acceptedByUserId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'accepted_by_user_id', type: 'string', length: 36, nullable: true)]
  public ?string $acceptedByUserId = null;

  /**
   * Property revokedByUserId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'revoked_by_user_id', type: 'string', length: 36, nullable: true)]
  public ?string $revokedByUserId = null;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'status', type: 'string', length: 20)]
  public string $status = 'pending';

  /**
   * Property expiresAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
  public DateTimeImmutable $expiresAt;

  /**
   * Property acceptedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'accepted_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $acceptedAt = null;

  /**
   * Property revokedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $revokedAt = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
  // #endregion
}
