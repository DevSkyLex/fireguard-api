<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityDescendants;

use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Shared\Application\Message\ResultMessage;

final readonly class GetFacilityDescendantsResult implements ResultMessage
{
  /**
   * @param list<GetFacilityResult> $items
   */
  public function __construct(
    public array $items,
  ) {
  }
}
