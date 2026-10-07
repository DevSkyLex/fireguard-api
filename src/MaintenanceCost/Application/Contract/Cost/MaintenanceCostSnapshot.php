<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Contract\Cost;

/** Class MaintenanceCostSnapshot. Private immutable version frozen with the operational publication. @category Contract */
final readonly class MaintenanceCostSnapshot
{
  public function __construct(public int $version, public string $capturedAt, public string $publicationId, public int $interventionRevision, public string $currency, public MaintenanceCostTotals $totals, public ?MaintenanceCostPlanning $planning = null)
  {
  }
}
