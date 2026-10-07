<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Query\GetServiceRequest;

use ServiceRequest\Application\Contract\ServiceRequestView;
use Shared\Application\Message\ResultMessage;

/** Class GetServiceRequestResult. Persisted request projection. @category Result */
final readonly class GetServiceRequestResult implements ResultMessage
{
  public function __construct(public ServiceRequestView $request)
  {
  }
}
