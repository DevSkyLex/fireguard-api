<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Main-database persistence record for multi-operation maintenance. */
#[ORM\Entity]
#[ORM\Table(name: 'maintenance_occurrences')]
#[ORM\UniqueConstraint(name: 'uniq_maintenance_occurrence_open', columns: ['plan_id'], options: ['where' => "((status)::text = 'open'::text)"])]
#[ORM\Index(name: 'idx_maintenance_occurrence_org_plan', columns: ['organization_id', 'plan_id'])]
class MaintenanceOccurrenceRecord
{
  #[ORM\Column(type: 'integer', nullable: true)]
  public ?int $number = null;

  #[ORM\Id]
  #[ORM\Column(name: 'id', type: 'string', length: 36)]
  public string $id;

  #[ORM\Column(name: 'plan_id', type: 'string', length: 36)]
  public string $planId;

  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'due_at', type: 'datetime_immutable')]
  public DateTimeImmutable $dueAt;

  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status;

  #[ORM\Column(name: 'attempt', type: 'integer')]
  public int $attempt;

  #[ORM\Column(name: 'intervention_id', type: 'string', length: 36, nullable: true)]
  public ?string $interventionId = null;

  #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $completedAt = null;

  #[ORM\Column(name: 'result_id', type: 'string', length: 36, nullable: true)]
  public ?string $resultId = null;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
}
