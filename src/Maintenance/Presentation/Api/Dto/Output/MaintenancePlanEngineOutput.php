<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Dto\Output;

use Symfony\Component\Serializer\Attribute\Groups;

/** HTTP contract for independent maintenance operations. */
final class MaintenancePlanEngineOutput
{
  #[Groups(['maintenance_plan:read'])]
  public string $mode = 'legacy';

  #[Groups(['maintenance_plan:read'])]
  public int $preparedCount = 0;

  /**
   * @var list<string>
   */
  #[Groups(['maintenance_plan:read'])]
  public array $conflicts = [];
}
