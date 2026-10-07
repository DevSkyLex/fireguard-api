<?php

declare(strict_types=1);

namespace Customer\Application\UseCase\Query\ListCustomers;

use Customer\Application\Contract\CustomerView;
use Customer\Application\Port\Outbound\CustomerRepositoryPort;
use Customer\Application\Service\CustomerAccessGuard;
use Customer\Domain\Exception\CustomerException;
use Shared\Application\Message\QueryHandler;

use function array_map;
use function mb_strlen;
use function trim;

/** Class ListCustomersHandler. Authorizes a bounded stable customer page. @category UseCase */
final readonly class ListCustomersHandler implements QueryHandler
{
  public function __construct(private CustomerRepositoryPort $customers, private CustomerAccessGuard $access)
  {
  }

  public function __invoke(ListCustomersQuery $query): ListCustomersResult
  {
    $this->access->assertAccess($query->actorId, $query->organizationId, false);
    if ($query->page < 1 || $query->itemsPerPage < 1 || $query->itemsPerPage > 100 || mb_strlen($query->search) > 160) {
      throw CustomerException::invalid('Invalid customer pagination or search.');
    }
    $search = trim($query->search);
    $items = $this->customers->list($query->organizationId, $search, $query->archived, ($query->page - 1) * $query->itemsPerPage, $query->itemsPerPage);

    return new ListCustomersResult(array_map(CustomerView::fromCustomer(...), $items), $this->customers->count($query->organizationId, $search, $query->archived), $query->page, $query->itemsPerPage);
  }
}
