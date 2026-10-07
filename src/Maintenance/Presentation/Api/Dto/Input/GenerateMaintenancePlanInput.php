<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** An explicit retry is distinct from a harmless repeat of generation. */
final class GenerateMaintenancePlanInput
{
  #[Groups(['maintenance_plan:write'])]
  public bool $retry = false;

  #[Groups(['maintenance_plan:write'])]
  #[Assert\Length(min: 1, max: 160)]
  public ?string $name = null;
}
