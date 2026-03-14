<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record OrganizationInvitationRoleRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization_invitation_roles')]
#[ORM\Index(name: 'idx_organization_invitation_roles_role', columns: ['role_id'])]
class OrganizationInvitationRoleRecord
{
  // #region Properties
  /**
   * Property invitation.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\ManyToOne(targetEntity: OrganizationInvitationRecord::class, inversedBy: 'roleAssignments')]
  #[ORM\JoinColumn(name: 'invitation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationInvitationRecord $invitation = null;

  /**
   * Property role.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\ManyToOne(targetEntity: OrganizationRoleRecord::class, inversedBy: 'invitationAssignments')]
  #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRoleRecord $role = null;

  /**
   * Property assignedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'assigned_at', type: 'datetime_immutable')]
  public DateTimeImmutable $assignedAt;
  // #endregion
}
