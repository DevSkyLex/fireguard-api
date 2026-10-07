<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Dto\Input\Cost;

use Symfony\Component\Serializer\Attribute\Groups;

/** Class CreateMaintenanceExpenseInput. Stable declaration identity and an exact signed adjustment. @category Input */
final class CreateMaintenanceExpenseInput
{
  #[Groups(['maintenance_cost:write'])]
  public string $clientId = '';

  #[Groups(['maintenance_cost:write'])]
  public string $amount = '';

  #[Groups(['maintenance_cost:write'])]
  public string $description = '';

  #[Groups(['maintenance_cost:write'])]
  public string $incurredAt = '';

  #[Groups(['maintenance_cost:write'])]
  public ?string $workItemId = null;

  #[Groups(['maintenance_cost:write'])]
  public ?string $adjustmentOf = null;
}
