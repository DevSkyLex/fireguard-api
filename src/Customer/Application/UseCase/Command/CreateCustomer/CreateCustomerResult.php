<?php

declare(strict_types=1);

namespace Customer\Application\UseCase\Command\CreateCustomer;

use Customer\Application\Contract\CustomerView;
use Shared\Application\Message\ResultMessage;

/** Class CreateCustomerResult. Newly persisted customer projection. @category Result */
final readonly class CreateCustomerResult implements ResultMessage
{
  public function __construct(public CustomerView $customer)
  {
  }
}
