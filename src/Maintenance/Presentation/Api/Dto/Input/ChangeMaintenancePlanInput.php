<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** New plans start prepared; calendar preview is available before enabling them. */
final class ChangeMaintenancePlanInput
{
  #[Groups(['maintenance_plan:write'])]
  #[Assert\Uuid]
  public ?string $equipmentId = null;

  #[Groups(['maintenance_plan:write'])]
  #[Assert\Length(min: 1, max: 160)]
  public ?string $name = null;

  #[Groups(['maintenance_plan:write'])]
  #[Assert\Choice(choices: ['control', 'maintenance'])]
  public ?string $operationKind = null;

  #[Groups(['maintenance_plan:write'])]
  #[Assert\Length(min: 3, max: 32)]
  public ?string $interval = null;

  #[Groups(['maintenance_plan:write'])]
  public ?string $anchorAt = null;

  #[Groups(['maintenance_plan:write'])]
  #[Assert\Date]
  public ?string $anchorOn = null;

  #[Groups(['maintenance_plan:write'])]
  public ?string $nextDueAt = null;

  #[Groups(['maintenance_plan:write'])]
  #[Assert\Date]
  public ?string $nextDueOn = null;

  #[Groups(['maintenance_plan:write'])]
  public ?bool $active = null;
}
