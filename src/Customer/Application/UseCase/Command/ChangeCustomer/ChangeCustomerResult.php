<?php

declare(strict_types=1);

namespace Customer\Application\UseCase\Command\ChangeCustomer;

use Customer\Application\Contract\CustomerView;
use Shared\Application\Message\ResultMessage;

/** Class ChangeCustomerResult. Persisted customer after its mutation. @category Result */
final readonly class ChangeCustomerResult implements ResultMessage
{
  public function __construct(public CustomerView $customer)
  {
  }
}
