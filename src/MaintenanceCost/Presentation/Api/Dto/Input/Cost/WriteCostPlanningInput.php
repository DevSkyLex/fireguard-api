<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Dto\Input\Cost;

use Symfony\Component\Serializer\Attribute\Groups;

/** Class WriteCostPlanningInput. Exact strings and explicit nullable forecast fields. @category Input */
final class WriteCostPlanningInput
{
  #[Groups(['maintenance_cost:write'])]
  public ?string $plannedBudget = null;

  #[Groups(['maintenance_cost:write'])]
  public ?int $estimatedMinutes = null;

  /**
   * @var list<array<string,mixed>>
   */
  #[Groups(['maintenance_cost:write'])]
  public array $resources = [];
}
