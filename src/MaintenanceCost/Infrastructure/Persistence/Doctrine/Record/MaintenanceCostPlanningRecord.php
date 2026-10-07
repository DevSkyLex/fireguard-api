<?php

declare(strict_types=1);

namespace MaintenanceCost\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/** Class MaintenanceCostPlanningRecord. Independent financial planning revision. @category Record */
#[ORM\Entity]
#[ORM\Table(name: 'maintenance_cost_planning')]
class MaintenanceCostPlanningRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'organization_id', length: 36)]
  public string $organizationId;

  #[ORM\Id]
  #[ORM\Column(name: 'intervention_id', length: 36)]
  public string $interventionId;

  #[ORM\Column(name: 'planned_budget', type: 'decimal', precision: 24, scale: 6, nullable: true)]
  public ?string $plannedBudget = null;

  #[ORM\Column(name: 'estimated_minutes', type: 'integer', nullable: true)]
  public ?int $estimatedMinutes = null;

  /**
   * @var list<array{workItemId:?string,kind:string,description:string,quantity:?string,unitCost:?string,estimatedMinutes:?int,amount:?string}>
   */
  #[ORM\Column(type: 'json')]
  public array $resources = [];

  #[ORM\Column(type: 'integer')]
  public int $revision = 0;
}
