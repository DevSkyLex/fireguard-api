<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record OrganizationMemberRoleRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization_member_roles')]
#[ORM\Index(name: 'idx_organization_member_roles_role', columns: ['role_id'])]
class OrganizationMemberRoleRecord
{
  // #region Properties
  /**
   * Property member.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\ManyToOne(targetEntity: OrganizationMemberRecord::class, inversedBy: 'roleAssignments')]
  #[ORM\JoinColumn(name: 'member_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationMemberRecord $member = null;

  /**
   * Property role.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\ManyToOne(targetEntity: OrganizationRoleRecord::class, inversedBy: 'memberAssignments')]
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
