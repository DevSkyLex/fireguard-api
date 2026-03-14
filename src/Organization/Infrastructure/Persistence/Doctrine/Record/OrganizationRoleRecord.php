<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;

/**
 * Record OrganizationRoleRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization_roles')]
#[ORM\Index(name: 'idx_organization_role_organization', columns: ['organization_id'])]
#[ORM\UniqueConstraint(name: 'uniq_organization_role_name', columns: ['organization_id', 'name'])]
class OrganizationRoleRecord
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
  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class, inversedBy: 'roles')]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 50)]
  public string $name;

  /**
   * @var list<string>
   */
  #[ORM\Column(type: 'json')]
  public array $permissions = [];

  /**
   * Property isSystem.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'is_system', type: 'boolean')]
  public bool $isSystem = false;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property memberAssignments.
   *
   * @var Collection<int, OrganizationMemberRoleRecord>
   */
  #[ORM\OneToMany(mappedBy: 'role', targetEntity: OrganizationMemberRoleRecord::class, cascade: ['remove'])]
  public Collection $memberAssignments;

  /**
   * Property invitationAssignments.
   *
   * @var Collection<int, OrganizationInvitationRoleRecord>
   */
  #[ORM\OneToMany(mappedBy: 'role', targetEntity: OrganizationInvitationRoleRecord::class, cascade: ['remove'])]
  public Collection $invitationAssignments;

  /**
   * Constructor.
   */
  public function __construct()
  {
    $this->memberAssignments = new ArrayCollection();
    $this->invitationAssignments = new ArrayCollection();
  }
  // #endregion
}
