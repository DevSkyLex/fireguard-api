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
#[ORM\Index(name: 'idx_organization_plan', columns: ['plan_id'])]
#[ORM\Index(name: 'idx_organization_purge_scheduled_at', columns: ['purge_scheduled_at'])]
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
   * Property description.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'description', type: 'text', nullable: true)]
  public ?string $description = null;

  /**
   * Property logoUrl.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'logo_url', type: 'string', length: 500, nullable: true)]
  public ?string $logoUrl = null;

  /**
   * Property settings.
   *
   * Structured organization preferences (notifications, regional) persisted as
   * a JSON blob.
   *
   * @var array<string, mixed>
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'settings', type: 'json', options: ['default' => '{}'])]
  public array $settings = [];

  /**
   * Property planId.
   *
   * Soft reference to the assigned subscription plan. Null falls back to the
   * catalog default plan when resolving features.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'plan_id', type: 'string', length: 36, nullable: true)]
  public ?string $planId = null;

  /**
   * Property country.
   *
   * ISO 3166-1 alpha-2 legal country code. Part of the organization's legal
   * profile (L3.1) — read/written through {@see \Organization\Domain\ValueObject\OrganizationCountry}.
   *
   * @since 1.1.0
   */
  #[ORM\Column(name: 'country', type: 'string', length: 2, nullable: true)]
  public ?string $country = null;

  /**
   * Property legalType.
   *
   * Legal entity type (e.g. company form). Part of the organization's legal
   * profile (L3.1) — read/written through {@see \Organization\Domain\ValueObject\OrganizationLegalType}.
   *
   * @since 1.1.0
   */
  #[ORM\Column(name: 'legal_type', type: 'string', length: 32, nullable: true)]
  public ?string $legalType = null;

  /**
   * Property legalName.
   *
   * Registered legal name, which may differ from the organization's display
   * `name`. Part of the organization's legal profile (L3.1).
   *
   * @since 1.1.0
   */
  #[ORM\Column(name: 'legal_name', type: 'string', length: 255, nullable: true)]
  public ?string $legalName = null;

  /**
   * Property registrationNumber.
   *
   * Company/registration number in the jurisdiction identified by `country`.
   * Part of the organization's legal profile (L3.1) — read/written through
   * {@see \Organization\Domain\ValueObject\OrganizationRegistrationNumber}.
   *
   * @since 1.1.0
   */
  #[ORM\Column(name: 'registration_number', type: 'string', length: 64, nullable: true)]
  public ?string $registrationNumber = null;

  /**
   * Property vatNumber.
   *
   * Part of the organization's legal profile (L3.1) — read/written through
   * {@see \Organization\Domain\ValueObject\OrganizationVatNumber}.
   *
   * @since 1.1.0
   */
  #[ORM\Column(name: 'vat_number', type: 'string', length: 32, nullable: true)]
  public ?string $vatNumber = null;

  /**
   * Property purgeScheduledAt.
   *
   * NULL means "not scheduled for purge". Archiving an organization does
   * NOT, by itself, set this — lot L3.3 owns the policy deciding when an
   * archived organization becomes purge-eligible. Once set, the organization
   * becomes eligible for permanent purge after this instant elapses.
   * Scaffolded inert by lot L3.0; activated by lot L3.3.
   *
   * @since 1.1.0
   */
  #[ORM\Column(name: 'purge_scheduled_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $purgeScheduledAt = null;

  /**
   * Property purgedAt.
   *
   * NULL means "not yet purged". Set once the purge has completed, which
   * makes the purge sweep idempotent and resumable — it spans two databases
   * with no global transaction. Scaffolded inert by lot L3.0; activated by
   * lot L3.3.
   *
   * @since 1.1.0
   */
  #[ORM\Column(name: 'purged_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $purgedAt = null;

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
