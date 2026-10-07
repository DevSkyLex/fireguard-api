<?php

declare(strict_types=1);

namespace MaintenanceExport\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class AdjustMaintenanceExportInput
 * Stable operation identities distinguish explicit actions from transport retries.
 *
 * @category Input
 */
final class AdjustMaintenanceExportInput
{
  // #region Properties
  #[Groups(['maintenance_export:write'])]
  #[Assert\NotBlank()]
  #[Assert\Uuid()]
  public string $clientOperationId = '';

  #[Groups(['maintenance_export:write'])]
  #[Assert\NotBlank()]
  #[Assert\Length(max: 1000)]
  public string $reason = '';

  // #endregion
}
