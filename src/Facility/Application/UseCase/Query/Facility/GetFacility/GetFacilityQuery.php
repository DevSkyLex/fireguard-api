<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacility;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetFacilityQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $facilityId,
  ) {
  }
  // #endregion
}
