<?php

declare(strict_types=1);

namespace Customer\Application\UseCase\Query\GetCustomer;

use Customer\Application\Contract\CustomerView;
use Customer\Application\Port\Outbound\CustomerRepositoryPort;
use Customer\Application\Service\CustomerAccessGuard;
use Customer\Domain\Exception\CustomerException;
use Shared\Application\Message\QueryHandler;

/** Class GetCustomerHandler. Checks scope and read permission before exposing customer data. @category UseCase */
final readonly class GetCustomerHandler implements QueryHandler
{
  public function __construct(private CustomerRepositoryPort $customers, private CustomerAccessGuard $access)
  {
  }

  public function __invoke(GetCustomerQuery $query): GetCustomerResult
  {
    $this->access->assertAccess($query->actorId, $query->organizationId, false);
    $customer = $this->customers->find($query->customerId, $query->organizationId);
    if (null === $customer) {
      throw CustomerException::notFound();
    }

    return new GetCustomerResult(CustomerView::fromCustomer($customer));
  }
}
