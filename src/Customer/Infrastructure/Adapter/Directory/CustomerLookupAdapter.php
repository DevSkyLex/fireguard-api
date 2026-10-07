<?php

declare(strict_types=1);

namespace Customer\Infrastructure\Adapter\Directory;

use Customer\Application\Contract\CustomerSnapshot;
use Customer\Application\Port\Inbound\CustomerLookupPort;
use Customer\Application\Port\Outbound\CustomerRepositoryPort;

/** Class CustomerLookupAdapter. Exposes owner-scoped customer identity without persistence objects. @category Adapter */
final readonly class CustomerLookupAdapter implements CustomerLookupPort
{
  public function __construct(private CustomerRepositoryPort $customers)
  {
  }

  public function find(string $customerId, string $organizationId): ?CustomerSnapshot
  {
    $customer = $this->customers->find($customerId, $organizationId);

    return null === $customer ? null : new CustomerSnapshot($customer->id, $customer->name, $customer->contacts, $customer->archivedAt);
  }
}
