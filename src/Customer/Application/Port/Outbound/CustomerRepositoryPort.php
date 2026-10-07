<?php

declare(strict_types=1);

namespace Customer\Application\Port\Outbound;

use Customer\Domain\Model\Customer\Customer;

/** Interface CustomerRepositoryPort. All reads and writes remain organization scoped. @category Port */
interface CustomerRepositoryPort
{
  public function find(string $id, string $organizationId, bool $lock = false): ?Customer;

  public function save(Customer $customer, ?int $expectedRevision = null): void;

  /**
   * @return list<Customer>
   */
  public function list(string $organizationId, string $search, bool $archived, int $offset, int $limit): array;

  public function count(string $organizationId, string $search, bool $archived): int;
}
