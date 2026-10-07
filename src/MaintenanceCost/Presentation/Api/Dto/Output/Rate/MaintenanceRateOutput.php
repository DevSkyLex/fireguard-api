<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Dto\Output\Rate;

use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;
use Symfony\Component\Serializer\Attribute\Groups;

/** Private exact hourly amount, expressed in the organization's current common currency. */
final class MaintenanceRateOutput
{
  #[Groups(['maintenance_cost_rate:read'])]
  public string $id = '';

  #[Groups(['maintenance_cost_rate:read'])]
  public string $memberId = '';

  #[Groups(['maintenance_cost_rate:read'])]
  public string $hourlyAmount = '';

  #[Groups(['maintenance_cost_rate:read'])]
  public string $currency = '';

  #[Groups(['maintenance_cost_rate:read'])]
  public string $effectiveFrom = '';

  #[Groups(['maintenance_cost_rate:read'])]
  public bool $replayed = false;

  public static function fromSnapshot(MaintenanceRateSnapshot $snapshot, bool $replayed = false): self
  {
    $output = new self();
    $output->id = $snapshot->id;
    $output->memberId = $snapshot->memberId;
    $output->hourlyAmount = $snapshot->hourlyAmount;
    $output->currency = $snapshot->currency;
    $output->effectiveFrom = $snapshot->effectiveFrom;
    $output->replayed = $replayed;

    return $output;
  }
}
