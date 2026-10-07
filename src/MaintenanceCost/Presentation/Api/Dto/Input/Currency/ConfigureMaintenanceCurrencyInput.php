<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Dto\Input\Currency;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Currency configuration is explicit and cannot convert already captured facts. */
final class ConfigureMaintenanceCurrencyInput
{
  #[Groups(['maintenance_cost_currency:write'])]
  #[Assert\NotBlank]
  #[Assert\Regex(pattern: '/^[A-Z]{3}$/D')]
  public string $currency = '';
}
