<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Dto\Output;

use Symfony\Component\Serializer\Attribute\Groups;

/** HTTP contract for independent maintenance operations. */
final class GenerateMaintenancePlanOutput
{
  #[Groups(['maintenance_plan:read'])]
  public string $occurrenceId = '';

  #[Groups(['maintenance_plan:read'])]
  public string $interventionId = '';

  #[Groups(['maintenance_plan:read'])]
  public int $number = 0;

  #[Groups(['maintenance_plan:read'])]
  public int $workItemsCount = 0;

  #[Groups(['maintenance_plan:read'])]
  public bool $replayed = false;
}
