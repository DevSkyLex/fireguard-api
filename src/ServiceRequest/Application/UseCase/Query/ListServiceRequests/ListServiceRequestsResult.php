<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Query\ListServiceRequests;

use ServiceRequest\Application\Contract\ServiceRequestView;
use Shared\Application\Message\ResultMessage;

/** Class ListServiceRequestsResult. Scoped request page with matching total. @category Result */
final readonly class ListServiceRequestsResult implements ResultMessage
{
  /**
   * @param list<ServiceRequestView> $items
   */
  public function __construct(public array $items, public int $total, public int $page, public int $itemsPerPage)
  {
  }
}
