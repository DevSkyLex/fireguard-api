<?php

declare(strict_types=1);

namespace MaintenanceExport\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ConfirmMaintenanceExportInput
 * Stable operation identities distinguish explicit actions from transport retries.
 *
 * @category Input
 */
final class ConfirmMaintenanceExportInput
{
  // #region Properties
  #[Groups(['maintenance_export:write'])]
  #[Assert\NotBlank()]
  #[Assert\Uuid()]
  public string $clientOperationId = '';

  #[Groups(['maintenance_export:write'])]
  #[Assert\NotBlank()]
  #[Assert\Length(max: 200)]
  public string $externalImportReference = '';

  // #endregion
}
