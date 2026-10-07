<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Contract\Cost;

/** Class MaintenanceCostView. Dedicated financial read model excluded from ordinary dossiers. @category Contract */
final readonly class MaintenanceCostView
{
  public function __construct(public string $interventionId, public string $organizationId, public string $currency, public MaintenanceCostPlanning $planning, public MaintenanceCostTotals $current, public ?MaintenanceCostSnapshot $frozen, public bool $planningEditable = false)
  {
  }
}
