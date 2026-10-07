<?php

declare(strict_types=1);

namespace Customer\Infrastructure\Adapter\Facility;

use Customer\Application\Port\Outbound\CustomerRepositoryPort;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Contract\FacilityCustomerUnavailable;
use Facility\Application\Port\Outbound\FacilityCustomerValidationPort;

/** Class FacilityCustomerValidationAdapter. Locks active owner-scoped customers during main assignments. @category Adapter */
final readonly class FacilityCustomerValidationAdapter implements FacilityCustomerValidationPort
{
  public function __construct(private CustomerRepositoryPort $customers, private EntityManagerInterface $entityManager)
  {
  }

  public function assertAssignable(string $customerId, string $organizationId): void
  {
    $customer = $this->customers->find($customerId, $organizationId, $this->entityManager->getConnection()->isTransactionActive());
    if (null === $customer || null !== $customer->archivedAt) {
      throw new FacilityCustomerUnavailable();
    }
  }
}
