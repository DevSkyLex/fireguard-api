<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

#[ORM\Entity]
#[ORM\Table(name: 'checklists')]
#[ORM\Index(name: 'idx_checklist_organization', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_checklist_status', columns: ['status'])]
#[ORM\Index(name: 'idx_checklist_organization_status', columns: ['organization_id', 'status'])]
class ChecklistRecord
{
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class, inversedBy: 'checklists')]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  #[ORM\Column(name: 'name', type: 'string', length: 255)]
  public string $name;

  #[ORM\Column(name: 'version', type: 'string', length: 50)]
  public string $version;

  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;

  /**
   * @var Collection<int, ChecklistItemRecord>
   */
  #[ORM\OneToMany(mappedBy: 'checklist', targetEntity: ChecklistItemRecord::class, cascade: ['remove'])]
  public Collection $items;

  public function __construct()
  {
    $this->items = new ArrayCollection();
  }
}
