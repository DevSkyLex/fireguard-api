<?php

declare(strict_types=1);

namespace Customer\Application\UseCase\Query\ListCustomers;

use Customer\Application\Contract\CustomerView;
use Shared\Application\Message\ResultMessage;

/** Class ListCustomersResult. Customer page and total for Hydra pagination. @category Result */
final readonly class ListCustomersResult implements ResultMessage
{
  /**
   * @param list<CustomerView> $items
   */
  public function __construct(public array $items, public int $total, public int $page, public int $itemsPerPage)
  {
  }
}
