<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'non_conformities')]
#[ORM\Index(name: 'idx_non_conformity_inspection', columns: ['inspection_id'])]
#[ORM\Index(name: 'idx_non_conformity_severity', columns: ['severity'])]
#[ORM\Index(name: 'idx_non_conformity_status', columns: ['status'])]
#[ORM\Index(name: 'idx_non_conformity_inspection_severity', columns: ['inspection_id', 'severity'])]
#[ORM\Index(name: 'idx_non_conformity_inspection_status', columns: ['inspection_id', 'status'])]
#[ORM\Index(name: 'idx_non_conformity_inspection_created_at', columns: ['inspection_id', 'created_at'])]
#[ORM\Index(name: 'idx_non_conformity_inspection_resolved_at', columns: ['inspection_id', 'resolved_at'])]
#[ORM\Index(name: 'idx_non_conformity_inspection_status_due_at', columns: ['inspection_id', 'status', 'due_at'])]
#[ORM\Index(name: 'idx_non_conformity_created_at_inspection', columns: ['created_at', 'inspection_id'])]
#[ORM\Index(name: 'idx_non_conformity_resolved_at_inspection_not_null', columns: ['resolved_at', 'inspection_id'], options: ['where' => '(resolved_at IS NOT NULL)'])]
class NonConformityRecord
{
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  #[ORM\ManyToOne(targetEntity: InspectionRecord::class, inversedBy: 'nonConformities')]
  #[ORM\JoinColumn(name: 'inspection_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?InspectionRecord $inspection = null;

  #[ORM\Column(name: 'description', type: 'text')]
  public string $description;

  #[ORM\Column(name: 'severity', type: 'string', length: 16)]
  public string $severity;

  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status;

  #[ORM\Column(name: 'due_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $dueAt = null;

  #[ORM\Column(name: 'resolved_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $resolvedAt = null;

  #[ORM\Column(name: 'notes', type: 'text', nullable: true)]
  public ?string $notes = null;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
}
