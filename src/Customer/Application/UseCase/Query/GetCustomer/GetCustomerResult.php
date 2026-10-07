<?php

declare(strict_types=1);

namespace Customer\Application\UseCase\Query\GetCustomer;

use Customer\Application\Contract\CustomerView;
use Shared\Application\Message\ResultMessage;

/** Class GetCustomerResult. Scoped customer projection. @category Result */
final readonly class GetCustomerResult implements ResultMessage
{
  public function __construct(public CustomerView $customer)
  {
  }
}
