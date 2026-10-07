<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Dto\Output;

use Symfony\Component\Serializer\Attribute\Groups;

/** HTTP contract for independent maintenance operations. */
final class MaintenanceOccurrenceOutput
{
  #[Groups(['maintenance_plan:read'])]
  public string $id = '';

  #[Groups(['maintenance_plan:read'])]
  public string $dueAt = '';

  #[Groups(['maintenance_plan:read'])]
  public int $attempt = 0;

  #[Groups(['maintenance_plan:read'])]
  public ?string $interventionId = null;

  #[Groups(['maintenance_plan:read'])]
  public ?int $number = null;

  #[Groups(['maintenance_plan:read'])]
  public string $status = 'open';

  #[Groups(['maintenance_plan:read'])]
  public bool $retryAllowed = false;
}
