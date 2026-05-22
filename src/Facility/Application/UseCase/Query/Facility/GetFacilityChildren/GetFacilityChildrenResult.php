<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityChildren;

use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Shared\Application\Message\ResultMessage;

final readonly class GetFacilityChildrenResult implements ResultMessage
{
  /**
   * @param list<GetFacilityResult> $items
   */
  public function __construct(
    public array $items,
  ) {
  }
}
