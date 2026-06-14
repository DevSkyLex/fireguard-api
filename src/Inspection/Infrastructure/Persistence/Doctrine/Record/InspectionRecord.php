<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Record InspectionRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'inspections')]
#[ORM\Index(name: 'idx_inspection_organization', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_inspection_equipment', columns: ['equipment_id'])]
#[ORM\Index(name: 'idx_inspection_facility', columns: ['facility_id'])]
#[ORM\Index(name: 'idx_inspection_result', columns: ['result'])]
#[ORM\Index(name: 'idx_inspection_status', columns: ['status'])]
#[ORM\Index(name: 'idx_inspection_organization_equipment', columns: ['organization_id', 'equipment_id'])]
#[ORM\Index(name: 'idx_inspection_organization_performed_at', columns: ['organization_id', 'performed_at'])]
#[ORM\Index(name: 'idx_inspection_organization_inspector_type_performed_at', columns: ['organization_id', 'inspector_type', 'performed_at'])]
#[ORM\Index(name: 'idx_inspection_organization_result', columns: ['organization_id', 'result'])]
#[ORM\Index(name: 'idx_inspection_organization_status', columns: ['organization_id', 'status'])]
#[ORM\Index(name: 'idx_inspection_intervention_record_status', columns: ['intervention_id', 'record_status'])]
class InspectionRecord
{
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
  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class, inversedBy: 'inspections')]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  /**
   * Property interventionId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'intervention_id', type: 'string', length: 36, nullable: true)]
  public ?string $interventionId = null;

  /**
   * Property clientId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'client_id', type: 'string', length: 36, nullable: true, unique: true)]
  public ?string $clientId = null;

  /**
   * Property recordStatus.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'record_status', type: 'string', length: 16)]
  public string $recordStatus = 'published';

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'revision', type: 'integer')]
  public int $revision = 1;

  /**
   * Property equipmentId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'equipment_id', type: 'string', length: 36)]
  public string $equipmentId;

  /**
   * Property facilityId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'facility_id', type: 'string', length: 36, nullable: true)]
  public ?string $facilityId = null;

  /**
   * Property inspectorType.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'inspector_type', type: 'string', length: 16)]
  public string $inspectorType;

  /**
   * Property inspectorName.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'inspector_name', type: 'string', length: 255)]
  public string $inspectorName;

  /**
   * Property inspectorUserId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'inspector_user_id', type: 'string', length: 36, nullable: true)]
  public ?string $inspectorUserId = null;

  /**
   * Property inspectorOrganizationName.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'inspector_organization_name', type: 'string', length: 255, nullable: true)]
  public ?string $inspectorOrganizationName = null;

  /**
   * Property result.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'result', type: 'string', length: 16)]
  public string $result;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status;

  /**
   * Property performedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'performed_at', type: 'datetime_immutable')]
  public DateTimeImmutable $performedAt;

  /**
   * Property checklistId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'checklist_id', type: 'string', length: 36, nullable: true)]
  public ?string $checklistId = null;

  /**
   * Property notes.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'notes', type: 'text', nullable: true)]
  public ?string $notes = null;

  /**
   * Property signature.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'signature', type: 'text', nullable: true)]
  public ?string $signature = null;

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
   * @var Collection<int, NonConformityRecord>
   */
  #[ORM\OneToMany(mappedBy: 'inspection', targetEntity: NonConformityRecord::class, cascade: ['remove'])]
  public Collection $nonConformities;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InspectionRecord class.
   *
   * @since 1.0.0
   */
  public function __construct()
  {
    $this->nonConformities = new ArrayCollection();
  }
}
