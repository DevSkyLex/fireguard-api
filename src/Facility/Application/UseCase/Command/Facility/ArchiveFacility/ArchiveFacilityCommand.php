<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\ArchiveFacility;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase ArchiveFacilityCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ArchiveFacilityCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $facilityId,
  ) {
  }
  // #endregion
}
