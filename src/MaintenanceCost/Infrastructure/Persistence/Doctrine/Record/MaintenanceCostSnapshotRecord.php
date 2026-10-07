<?php

declare(strict_types=1);

namespace MaintenanceCost\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/** Class MaintenanceCostSnapshotRecord. Private frozen publication costs never included in operational resources. @category Record */
#[ORM\Entity]
#[ORM\Table(name: 'maintenance_cost_snapshots')]
#[ORM\UniqueConstraint(name: 'uniq_maintenance_cost_publication', columns: ['publication_id'])]
class MaintenanceCostSnapshotRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'organization_id', length: 36)]
  public string $organizationId;

  #[ORM\Id]
  #[ORM\Column(name: 'intervention_id', length: 36)]
  public string $interventionId;

  #[ORM\Column(name: 'publication_id', length: 36)]
  public string $publicationId;

  #[ORM\Column(name: 'intervention_revision', type: 'integer')]
  public int $interventionRevision;

  #[ORM\Column(name: 'captured_at', length: 40)]
  public string $capturedAt;

  #[ORM\Column(type: 'integer')]
  public int $version = 1;

  #[ORM\Column(length: 3)]
  public string $currency;

  #[ORM\Column(type: 'decimal', precision: 38, scale: 6, nullable: true)]
  public ?string $total = null;

  #[ORM\Column(name: 'known_total', type: 'decimal', precision: 38, scale: 6)]
  public string $knownTotal;

  #[ORM\Column(type: 'boolean')]
  public bool $complete;

  #[ORM\Column(name: 'planned_budget', type: 'decimal', precision: 24, scale: 6, nullable: true)]
  public ?string $plannedBudget = null;

  #[ORM\Column(name: 'estimated_minutes', type: 'integer', nullable: true)]
  public ?int $estimatedMinutes = null;

  #[ORM\Column(name: 'planning_revision', type: 'integer')]
  public int $planningRevision = 0;

  /**
   * @var list<array{workItemId:?string,kind:string,description:string,quantity:?string,unitCost:?string,estimatedMinutes:?int,amount:?string}>
   */
  #[ORM\Column(name: 'planning_resources', type: 'json')]
  public array $planningResources = [];

  /**
   * @var list<array{id:string,kind:string,workItemId:?string,sourceId:string,sourceRevision:?int,amount:?string,currency:string,description:string,occurredAt:string,correctionOf:?string,hourlyAmount:?string,rateId:?string,equipmentId?:?string,allocation?:?array{identityState:string,equipment:?array{id:string,name:?string,assetReference:?string},site:?array{id:string,name:string},customer:?array{id:string,name:string}}}>
   */
  #[ORM\Column(type: 'json')]
  public array $items = [];
}
