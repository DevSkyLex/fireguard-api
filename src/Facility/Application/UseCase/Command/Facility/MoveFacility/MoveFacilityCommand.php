<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\MoveFacility;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase MoveFacilityCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MoveFacilityCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public ?string $parentFacilityId = null,
  ) {
  }
  // #endregion
}
