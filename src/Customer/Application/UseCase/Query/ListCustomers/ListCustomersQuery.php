<?php

declare(strict_types=1);

namespace Customer\Application\UseCase\Query\ListCustomers;

use Shared\Application\Message\QueryMessage;

/** Class ListCustomersQuery. Bounded customer search with active or archived selection. @category Query */
final readonly class ListCustomersQuery implements QueryMessage
{
  public function __construct(public string $actorId, public string $organizationId, public string $search = '', public bool $archived = false, public int $page = 1, public int $itemsPerPage = 30)
  {
  }
}
