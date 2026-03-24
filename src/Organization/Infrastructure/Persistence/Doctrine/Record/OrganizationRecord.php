<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentRecord, TagRecord};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{ChecklistRecord, InspectionRecord};

/**
 * Record OrganizationRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'organizations')]
#[ORM\Index(name: 'idx_organization_created_by_user', columns: ['created_by_user_id'])]
#[ORM\Index(name: 'idx_organization_owner_user', columns: ['owner_user_id'])]
#[ORM\Index(name: 'idx_organization_status', columns: ['status'])]
#[ORM\Index(name: 'idx_organization_status_name', columns: ['status', 'name'])]
#[ORM\UniqueConstraint(name: 'uniq_organization_slug', columns: ['slug'])]
class OrganizationRecord
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
   * Property name.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 120)]
  public string $name;

  /**
   * Property slug.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'slug', type: 'string', length: 120)]
  public string $slug;

  /**
   * Property ownerUserId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'owner_user_id', type: 'string', length: 36)]
  public string $ownerUserId;

  /**
   * Property createdByUserId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 36)]
  public string $createdByUserId;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'status', type: 'string', length: 20)]
  public string $status = 'active';

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'is_active', type: 'boolean')]
  public bool $isActive = true;

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

  /**
   * Property members.
   *
   * @var Collection<int, OrganizationMemberRecord>
   */
  #[ORM\OneToMany(mappedBy: 'organization', targetEntity: OrganizationMemberRecord::class, cascade: ['remove'])]
  public Collection $members;

  /**
   * Property roles.
   *
   * @var Collection<int, OrganizationRoleRecord>
   */
  #[ORM\OneToMany(mappedBy: 'organization', targetEntity: OrganizationRoleRecord::class, cascade: ['remove'])]
  public Collection $roles;

  /**
   * Property invitations.
   *
   * @var Collection<int, OrganizationInvitationRecord>
   */
  #[ORM\OneToMany(mappedBy: 'organization', targetEntity: OrganizationInvitationRecord::class, cascade: ['remove'])]
  public Collection $invitations;

  /**
   * Property legalProfile.
   *
   * @since 1.0.0
   */
  #[ORM\OneToOne(mappedBy: 'organization', targetEntity: OrganizationLegalProfileRecord::class, cascade: ['remove'])]
  public ?OrganizationLegalProfileRecord $legalProfile = null;

  /**
   * Property facilities.
   *
   * @var Collection<int, FacilityRecord>
   */
  #[ORM\OneToMany(mappedBy: 'organization', targetEntity: FacilityRecord::class, cascade: ['remove'])]
  public Collection $facilities;

  /**
   * Property tags.
   *
   * @var Collection<int, TagRecord>
   */
  #[ORM\OneToMany(mappedBy: 'organization', targetEntity: TagRecord::class, cascade: ['remove'])]
  public Collection $tags;

  /**
   * Property equipment.
   *
   * @var Collection<int, EquipmentRecord>
   */
  #[ORM\OneToMany(mappedBy: 'organization', targetEntity: EquipmentRecord::class, cascade: ['remove'])]
  public Collection $equipment;

  /**
   * Property checklists.
   *
   * @var Collection<int, ChecklistRecord>
   */
  #[ORM\OneToMany(mappedBy: 'organization', targetEntity: ChecklistRecord::class, cascade: ['remove'])]
  public Collection $checklists;

  /**
   * Property inspections.
   *
   * @var Collection<int, InspectionRecord>
   */
  #[ORM\OneToMany(mappedBy: 'organization', targetEntity: InspectionRecord::class, cascade: ['remove'])]
  public Collection $inspections;

  /**
   * Constructor.
   */
  public function __construct()
  {
    $this->members = new ArrayCollection();
    $this->roles = new ArrayCollection();
    $this->invitations = new ArrayCollection();
    $this->facilities = new ArrayCollection();
    $this->tags = new ArrayCollection();
    $this->equipment = new ArrayCollection();
    $this->checklists = new ArrayCollection();
    $this->inspections = new ArrayCollection();
  }
  // #endregion
}
