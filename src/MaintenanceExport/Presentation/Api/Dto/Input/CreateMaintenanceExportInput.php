<?php

declare(strict_types=1);

namespace MaintenanceExport\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class CreateMaintenanceExportInput
 * Stable operation identities distinguish explicit actions from transport retries.
 *
 * @category Input
 */
final class CreateMaintenanceExportInput
{
  // #region Properties
  #[Groups(['maintenance_export:write'])]
  #[Assert\NotBlank()]
  #[Assert\Uuid()]
  public string $clientOperationId = '';

  /**
   * @var list<string> published intervention identifiers
   */
  #[Groups(['maintenance_export:write'])]
  #[Assert\Count(min: 1, max: 100)]
  #[Assert\All([new Assert\Uuid()])]
  public array $interventionIds = [];

  #[Groups(['maintenance_export:write'])]
  #[Assert\NotBlank()]
  #[Assert\Length(max: 40)]
  public string $system = '';

  #[Groups(['maintenance_export:write'])]
  public bool $includeInternalCosts = false;

  // #endregion
}
