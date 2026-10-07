<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Query\ListServiceRequests;

use Shared\Application\Message\QueryMessage;

/** Class ListServiceRequestsQuery. Carries scoped search and pagination filters. @category Query */
final readonly class ListServiceRequestsQuery implements QueryMessage
{
  public function __construct(public string $actorId, public string $organizationId, public ?string $status = null, public ?string $equipmentId = null, public ?string $siteId = null, public string $search = '', public int $page = 1, public int $itemsPerPage = 30)
  {
  }
}
