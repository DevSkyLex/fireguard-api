<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\AssignToFacility;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AssignToFacilityCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssignToFacilityCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $facilityId,
    public ?string $installedAt = null,
  ) {
  }
  // #endregion
}
