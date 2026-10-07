<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Dto\Output\Currency;

use MaintenanceCost\Application\Contract\Currency\MaintenanceCurrencySnapshot;
use Symfony\Component\Serializer\Attribute\Groups;

/** Organization currency and immutable lock state; contains no financial amounts. */
final class MaintenanceCurrencyOutput
{
  #[Groups(['maintenance_cost_currency:read'])]
  public string $organizationId = '';

  #[Groups(['maintenance_cost_currency:read'])]
  public string $currency = 'EUR';

  #[Groups(['maintenance_cost_currency:read'])]
  public bool $locked = false;

  public static function fromSnapshot(MaintenanceCurrencySnapshot $snapshot): self
  {
    $output = new self();
    $output->organizationId = $snapshot->organizationId;
    $output->currency = $snapshot->currency;
    $output->locked = $snapshot->locked;

    return $output;
  }
}
