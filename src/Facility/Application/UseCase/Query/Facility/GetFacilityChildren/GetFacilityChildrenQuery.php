<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityChildren;

use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryMessage;

final readonly class GetFacilityChildrenQuery implements QueryMessage
{
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public bool $includeArchived = false,
    public ?string $search = null,
    public Sorting $sorting = new Sorting('name', SortDirection::ASC),
  ) {
  }
}
