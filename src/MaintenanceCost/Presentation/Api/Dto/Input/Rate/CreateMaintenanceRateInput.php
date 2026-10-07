<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Dto\Input\Rate;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Exact decimal strings and a stable UUID are required for idempotent rate creation. */
final class CreateMaintenanceRateInput
{
  #[Groups(['maintenance_cost_rate:write'])]
  #[Assert\NotBlank]
  #[Assert\Uuid]
  public string $clientId = '';

  #[Groups(['maintenance_cost_rate:write'])]
  #[Assert\NotBlank]
  #[Assert\Uuid]
  public string $memberId = '';

  #[Groups(['maintenance_cost_rate:write'])]
  #[Assert\NotBlank]
  #[Assert\Regex(pattern: '/^\d{1,18}(?:\.\d{1,6})?$/D')]
  public string $hourlyAmount = '';

  #[Groups(['maintenance_cost_rate:write'])]
  #[Assert\NotBlank]
  #[Assert\Date]
  public string $effectiveFrom = '';
}
