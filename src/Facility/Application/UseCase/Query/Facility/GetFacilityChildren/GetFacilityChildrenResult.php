<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityChildren;

use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Shared\Application\Message\ResultMessage;

/**
 * Class GetFacilityChildrenResult
 *
 * Carries the direct child facilities returned by the query.
 *
 * @category Result
 */
final readonly class GetFacilityChildrenResult implements ResultMessage
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
