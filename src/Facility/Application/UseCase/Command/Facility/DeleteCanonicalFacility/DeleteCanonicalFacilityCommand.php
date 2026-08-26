<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\DeleteCanonicalFacility;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteCanonicalFacilityCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCanonicalFacilityCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $facilityId,
    public int $expectedRevision,
  ) {
  }
  // #endregion
}
