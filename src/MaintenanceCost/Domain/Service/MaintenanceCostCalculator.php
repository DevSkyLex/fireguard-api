<?php

declare(strict_types=1);

namespace MaintenanceCost\Domain\Service;

use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use Shared\Domain\ValueObject\DecimalAmount;

use function bcadd;
use function is_numeric;
use function preg_match;

/** Class MaintenanceCostCalculator. Exact totals and hourly valuation with a single final division. @category Service */
final readonly class MaintenanceCostCalculator
{
  public function timeAmount(string $hourlyAmount, int $minutes): string
  {
    return DecimalAmount::fromString($hourlyAmount)->multiplyInt($minutes)->divideInt(60)->toString();
  }

  /**
   * @param list<?string> $amounts
   *
   * @return array{total:?string,knownTotal:string,complete:bool}
   */
  public function total(array $amounts): array
  {
    $known = '0.000000';
    $complete = true;
    foreach ($amounts as $amount) {
      if (null === $amount) {
        $complete = false;
      } else {
        if (1 !== preg_match('/^-?\d+(?:\.\d{1,6})?$/D', $amount) || !is_numeric($amount)) {
          throw MaintenanceCostException::invalid('A calculated cost must remain an exact decimal string.');
        }
        $known = bcadd($known, $amount, 6);
      }
    }

    return ['total' => $complete ? $known : null, 'knownTotal' => $known, 'complete' => $complete];
  }
}
