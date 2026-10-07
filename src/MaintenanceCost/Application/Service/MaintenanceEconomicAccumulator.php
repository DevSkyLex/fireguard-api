<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Service;

use MaintenanceCost\Application\Contract\Reporting\MaintenanceEconomicAmount;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;

use function bcadd;
use function is_numeric;

/**
 * Class MaintenanceEconomicAccumulator
 *
 * Sums arbitrary-size exact financial values while retaining unknown facts.
 *
 * @category Service
 */
final class MaintenanceEconomicAccumulator
{
  /**
   * @var numeric-string
   */
  private string $knownTotal = '0.000000';

  private int $count = 0;

  private int $unknown = 0;

  public function add(?string $amount): void
  {
    ++$this->count;
    if (null === $amount) {
      ++$this->unknown;
    } else {
      if (!is_numeric($amount)) {
        throw MaintenanceCostException::conflict('A financial source is not an exact numeric value.');
      }
      $this->knownTotal = bcadd($this->knownTotal, $amount, 6);
    }
  }

  public function amount(): MaintenanceEconomicAmount
  {
    return new MaintenanceEconomicAmount(0 === $this->unknown ? $this->knownTotal : null, $this->knownTotal, 0 === $this->unknown, $this->count, $this->unknown);
  }
}
