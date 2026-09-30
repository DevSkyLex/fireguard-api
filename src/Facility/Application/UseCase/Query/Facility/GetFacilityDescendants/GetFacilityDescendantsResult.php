<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityDescendants;

use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Shared\Application\Message\ResultMessage;

/**
 * Class GetFacilityDescendantsResult
 *
 * Carries the descendant facilities returned by the query.
 *
 * @category Result
 */
final readonly class GetFacilityDescendantsResult implements ResultMessage
{
  // #region Constructor
  /**
   * @param list<GetFacilityResult> $items
   */
  public function __construct(
    public array $items,
  ) {
  }
  // #endregion
}
