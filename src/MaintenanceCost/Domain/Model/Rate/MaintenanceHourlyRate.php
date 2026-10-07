<?php

declare(strict_types=1);

namespace MaintenanceCost\Domain\Model\Rate;

use DateTimeImmutable;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\DecimalAmount;

use function preg_match;

/** Exact, non-negative hourly rate parameters; historical facts retain the captured amount separately. */
final readonly class MaintenanceHourlyRate
{
  public string $hourlyAmount;

  public function __construct(public string $memberId, string $hourlyAmount, public string $effectiveFrom, public string $clientId)
  {
    foreach ([$memberId, $clientId] as $identifier) {
      if (1 !== preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D', $identifier)) {
        throw MaintenanceCostException::invalid('Rate member and client identifiers must be UUIDs.');
      }
    }

    try {
      $amount = DecimalAmount::fromString($hourlyAmount);
    } catch (InvalidValueException) {
      throw MaintenanceCostException::invalid('Hourly amount must be an exact decimal string with at most six decimal places.');
    }
    if ($amount->isNegative()) {
      throw MaintenanceCostException::invalid('Hourly amount must be non-negative.');
    }
    $this->hourlyAmount = $amount->toString();
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveFrom);
    if (false === $date || $date->format('Y-m-d') !== $effectiveFrom) {
      throw MaintenanceCostException::invalid('Rate effective date must be a valid YYYY-MM-DD date.');
    }
  }
}
