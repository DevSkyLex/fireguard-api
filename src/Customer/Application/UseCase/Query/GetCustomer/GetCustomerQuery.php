<?php

declare(strict_types=1);

namespace Customer\Application\UseCase\Query\GetCustomer;

use Shared\Application\Message\QueryMessage;

/** Class GetCustomerQuery. Reads one customer within its organization. @category Query */
final readonly class GetCustomerQuery implements QueryMessage
{
  public function __construct(public string $actorId, public string $organizationId, public string $customerId)
  {
  }
}
