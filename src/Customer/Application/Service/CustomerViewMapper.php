<?php

declare(strict_types=1);

namespace Customer\Application\Service;

use Customer\Application\Contract\CustomerView;
use Customer\Domain\Model\Customer\Customer;

/**
 * Service CustomerViewMapper.
 *
 * Builds the public read projection from the owning aggregate.
 *
 * @category Service
 */
final class CustomerViewMapper
{
  public static function fromCustomer(Customer $customer): CustomerView
  {
    return new CustomerView($customer->id, $customer->organizationId, $customer->name, $customer->code, $customer->email, $customer->phone, $customer->contacts, $customer->archivedAt, $customer->createdAt, $customer->updatedAt, $customer->revision);
  }
}
