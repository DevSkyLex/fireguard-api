<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

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
class InspectionRecord
{
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class, inversedBy: 'inspections')]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  #[ORM\Column(name: 'equipment_id', type: 'string', length: 36)]
  public string $equipmentId;

  #[ORM\Column(name: 'facility_id', type: 'string', length: 36, nullable: true)]
  public ?string $facilityId = null;

  #[ORM\Column(name: 'inspector_type', type: 'string', length: 16)]
  public string $inspectorType;

  #[ORM\Column(name: 'inspector_name', type: 'string', length: 255)]
  public string $inspectorName;

  #[ORM\Column(name: 'inspector_user_id', type: 'string', length: 36, nullable: true)]
  public ?string $inspectorUserId = null;

  #[ORM\Column(name: 'inspector_organization_name', type: 'string', length: 255, nullable: true)]
  public ?string $inspectorOrganizationName = null;

  #[ORM\Column(name: 'result', type: 'string', length: 16)]
  public string $result;

  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status;

  #[ORM\Column(name: 'performed_at', type: 'datetime_immutable')]
  public DateTimeImmutable $performedAt;

  #[ORM\Column(name: 'checklist_id', type: 'string', length: 36, nullable: true)]
  public ?string $checklistId = null;

  #[ORM\Column(name: 'notes', type: 'text', nullable: true)]
  public ?string $notes = null;

  #[ORM\Column(name: 'signature', type: 'text', nullable: true)]
  public ?string $signature = null;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;

  /**
   * @var Collection<int, NonConformityRecord>
   */
  #[ORM\OneToMany(mappedBy: 'inspection', targetEntity: NonConformityRecord::class, cascade: ['remove'])]
  public Collection $nonConformities;

  public function __construct()
  {
    $this->nonConformities = new ArrayCollection();
  }
}
