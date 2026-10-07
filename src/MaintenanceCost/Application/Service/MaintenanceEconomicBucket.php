<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Service;

use MaintenanceCost\Application\Contract\Reporting\MaintenanceEconomicRow;

use function array_keys;
use function bcsub;

/**
 * Class MaintenanceEconomicBucket
 *
 * Retains one row without multiplying a dossier amount across its targets.
 *
 * @category Service
 */
final class MaintenanceEconomicBucket
{
  public MaintenanceEconomicAccumulator $current;

  public MaintenanceEconomicAccumulator $frozen;

  public MaintenanceEconomicAccumulator $planned;

  public MaintenanceEconomicAccumulator $budget;

  /**
   * @var array<string,true>
   */
  private array $interventions = [];

  private string $state = '';

  private bool $allocationComplete = true;

  public function __construct(private ?string $id, private ?string $name)
  {
    $this->current = new MaintenanceEconomicAccumulator();
    $this->frozen = new MaintenanceEconomicAccumulator();
    $this->planned = new MaintenanceEconomicAccumulator();
    $this->budget = new MaintenanceEconomicAccumulator();
  }

  public function source(string $interventionId, string $state, bool $complete, ?string $name): void
  {
    $this->interventions[$interventionId] = true;
    $this->allocationComplete = $this->allocationComplete && $complete;
    if ('' === $this->state) {
      $this->state = $state;
    } elseif ($state !== $this->state) {
      $this->state = 'mixed';
    }
    if (null !== $this->name && null !== $name && $this->name !== $name) {
      $this->state = 'mixed';
    } elseif (null === $this->name) {
      $this->name = $name;
    }
  }

  public function row(): MaintenanceEconomicRow
  {
    $current = $this->current->amount();
    $planned = $this->planned->amount();
    $variance = $current->complete && $planned->complete && $planned->contributionCount > 0 ? bcsub($current->knownTotal, $planned->knownTotal, 6) : null;

    return new MaintenanceEconomicRow($this->id, $this->name, null === $this->id ? 'unallocated' : ('' === $this->state ? 'incomplete' : $this->state), null !== $this->id && $this->allocationComplete, $current, $this->frozen->amount(), $planned, $this->budget->amount(), $variance, array_keys($this->interventions));
  }
}
